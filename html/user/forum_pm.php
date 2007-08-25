<?php
$cvs_version_tracker[]="\$Id$";

require_once("../inc/forum.inc");
require_once("../inc/forum_std.inc");
require_once("../inc/email.inc");
require_once("../inc/akismet.inc");

db_init();

$action = get_str("action", true);
if ($action == null) { $action = post_str("action", true); }
if ($action == null) { $action = "inbox"; }

$logged_in_user = get_logged_in_user();

if ($action == "inbox") {
    page_head(tra("Private messages")." : ".tra("Inbox"));
    pm_header();
    
    if (get_int("sent", true) == 1) {
        echo "<div class=\"notice\">".tra("Your message has been sent.")."</div>\n";
    }
    
    $query = mysql_query("SELECT * FROM private_messages WHERE userid=".$logged_in_user->id." ORDER BY date DESC");
    if (mysql_num_rows($query) == 0) {
        echo tra("You have no private messages.");
    } else {
        start_table();
        print "<tr><th>".tra("Subject")."</th><th>".tra("Sender")."</th><th>".tra("Date")."</th></tr>\n";
        while ($row = mysql_fetch_object($query)) {
            print "<tr>\n";
            $subject = "<a href=\"forum_pm.php?action=read&id=".$row->id."\">".$row->subject."</a>";
            if ($row->opened) {
                print "<td>".$subject."</td>\n";
            } else {
                print "<td><strong>".$subject."</strong></td>\n";
            }
            print "<td>".user_links(get_user_from_id($row->senderid))."</td>\n";
            print "<td>".time_str($row->date)."</td>\n";
            print "</tr>\n";
        }
        end_table();
    }
} elseif ($action == "read") {
    $id = get_int("id");
    $message = mysql_query("SELECT * FROM private_messages WHERE id=".$id." AND userid=".$logged_in_user->id);
    if (mysql_num_rows($message) == 0) {
        error_page(tra("No such message"));
    } else {
        $message = mysql_fetch_object($message);
        page_head(tra("Private messages")." : ".$message->subject);
        pm_header();
        
        $options = new output_options;
        
        start_table();
        echo "<tr><th>".tra("Subject")."</th><td>".$message->subject."</td></tr>";
        echo "<tr><th>".tra("Sender")."</th><td>".user_links(get_user_from_id($message->senderid))." 
    <a href=\"forum_pm.php?action=block&amp;id=".$message->senderid."\"><img src=\"img/report_post.png\" width=\"9\" height=\"9\" alt=\"".tra("Block user")."\"></a></td></tr>";
        echo "<tr><th>".tra("Date")."</th><td>".time_str($message->date)."</td></tr>";
        echo "<tr><th>".tra("Message")."</th><td>".output_transform($message->content, $options)."</td></tr>";
        echo "<tr><td class=\"pm_footer\"></td><td>\n";
        echo "<a href=\"forum_pm.php?action=delete&amp;id=$id\">".tra("Delete")."</a>\n";
        echo " | <a href=\"forum_pm.php?action=new&amp;replyto=$id\">".tra("Reply")."</a>\n";
        echo " | <a href=\"forum_pm.php?action=inbox\">".tra("Inbox")."</a>\n";
        end_table();
        
        if ($message->opened == 0) {
            mysql_query("UPDATE private_messages SET opened=1 WHERE id=$id");
        }
    }

} elseif ($action == "new") {
    check_banished(new User($logged_in_user->id));
    pm_create_new();
} elseif ($action == "delete") {
    $id = get_int("id", true);
    if ($id == null) { $id = post_int("id"); }
    if (post_int("confirm", true) == 1) {
        check_tokens($logged_in_user->authenticator);
        mysql_query("DELETE FROM private_messages WHERE userid=".$logged_in_user->id." AND id=$id");
        header("Location: forum_pm.php");
    } else {
        $message = mysql_query("SELECT * FROM private_messages WHERE userid=".$logged_in_user->id." AND id=$id");
        if (mysql_num_rows($message) == 1) {
            $message = mysql_fetch_object($message);
            $sender = lookup_user_id($message->senderid);
            page_head(tra("Private messages")." : ".tra("Really delete?"));
            pm_header();
            echo "<div>".tra("Are you sure you want to delete the message with subject &quot;%1&quot; (sent by %2 on %3)?", $message->subject, $sender->name, time_str($message->date))."</div>\n";
            echo "<form action=\"forum_pm.php\" method=\"post\">\n";
            echo form_tokens($logged_in_user->authenticator);
            echo "<input type=\"hidden\" name=\"action\" value=\"delete\">\n";
            echo "<input type=\"hidden\" name=\"confirm\" value=\"1\">\n";
            echo "<input type=\"hidden\" name=\"id\" value=\"$id\">\n";
            echo "<input type=\"submit\" value=\"".tra("Yes, delete")."\">\n";
            echo "</form>\n";
            echo "<form action=\"forum_pm.php\" method=\"post\">\n";
            echo "<input type=\"hidden\" name=\"action\" value=\"inbox\">\n";
            echo "<input type=\"submit\" value=\"".tra("No, cancel")."\">\n";
            echo "</form>\n";
        } else {
            error_page(tra("No such message."));
        }
    }
} elseif ($action == "send") {
    check_banished(new User($logged_in_user->id));
    check_tokens($logged_in_user->authenticator);
    
    $to = stripslashes(post_str("to", true));
    $subject = stripslashes(post_str("subject", true));
    $content = stripslashes(post_str("content", true));
    
    if (post_str("preview", true) == tra("Preview")) {
        pm_create_new();
    }
    if (($to == null) || ($subject == null) || ($content == null)) {
        pm_create_new(tra("You need to fill all fields to send a private message"));
    } else {
        akismet_check(new User($logged_in_user->id), $content);
        $to = str_replace(", ", ",", $to); // Filter out spaces after separator
        $users = explode(",", $to);
        
        $userlist = array();
        $userids = array(); // To prevent from spamming a single user by adding it multiple times
        
        foreach ($users as $username) {
            $user = explode(" ", $username);
            if (is_numeric($user[0])) { // user ID is gived
                $userid = $user[0];
                $user = lookup_user_id($userid);
                if ($user == null) {
                    pm_create_new(tra("Could not find user with id %1", $userid));
                }
            } else {
                $user = lookup_user_name($username);
                if ($user == null) {
                    pm_create_new(tra("Could not find user with username %1", $username));
                } elseif ($user == -1) { // Non-unique username
                    pm_create_new(tra("%1 is not a unique username; you will have to use user ID", $username));
                }
            }
            $ignorelist = mysql_query("SELECT ignorelist FROM forum_preferences WHERE userid=".$user->id);
            $ignorelist = mysql_fetch_object($ignorelist);
            $ignorelist = $ignorelist->ignorelist;
            $ignorelist = explode("|", $ignorelist);
            if (in_array($logged_in_user->id, $ignorelist)) {
                pm_create_new(tra("User %1 (ID: %2) is not accepting private messages from you.", $user->name, $user->id));
            }
            if ($userids[$user->id] == null) {
                $userlist[] = $user;
                $userids[$user->id] = true;
            }
        }
        
        foreach ($userlist as $user) {
            check_pm_count($logged_in_user->id);
            pm_send($user, $subject, $content);
        }
        
        Header("Location: forum_pm.php?action=inbox&sent=1");
    }
} elseif ($action == "block") {
    $id = get_int("id");
    $user = mysql_query("SELECT name FROM user WHERE id=$id");
    if ($user) {
        $user = mysql_fetch_object($user);
        page_head(tra("Really block %1?", $user->name));
        echo "<div>".tra("Are you really sure you want to block user %1 from sending you private messages?", $user->name)."<br>\n";
        echo tra("Please note that you can only block a limited amount of users.")."</div>\n";
        echo "<div>".tra("Once the user has been blocked you can unblock it using forum preferences page.")."</div>\n";
        
        echo "<form action=\"forum_pm.php\" method=\"POST\">\n";
        echo form_tokens($logged_in_user->authenticator);
        echo "<input type=\"hidden\" name=\"action\" value=\"confirmedblock\">\n";
        echo "<input type=\"hidden\" name=\"id\" value=\"$id\">\n";
        echo "<input type=\"submit\" value=\"".tra("Add user to filter")."\">\n";
        echo "<a href=\"forum_pm.php?action=inbox\">".tra("No, cancel")."</a>\n";
        echo "</form>\n";
    } else {
        error_page(tra("No such user"));
    }
} elseif ($action == "confirmedblock") {
    check_tokens($logged_in_user->authenticator);
    $id = post_int("id");
    $user = new User($logged_in_user->id);
    $blocked = new User($id);
    $user->addIgnoredUser($blocked);
    
    page_head(tra("User %1 blocked", $blocked->getName()));
    
    echo "<div>".tra("User %1 has been blocked from sending you private messages.", $blocked->getName())."\n";
    echo tra("To unblock, visit %1message board preferences%2", "<a href=\"edit_forum_preferences_form.php\">", "</a>")."</div>\n";
}

page_tail();

?>
