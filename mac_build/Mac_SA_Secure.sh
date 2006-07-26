#! /bin/sh

# Berkeley Open Infrastructure for Network Computing
# http://boinc.berkeley.edu
# Copyright (C) 2006 University of California
#
# This is free software; you can redistribute it and/or
# modify it under the terms of the GNU Lesser General Public
# License as published by the Free Software Foundation;
# either version 2.1 of the License, or (at your option) any later version.
#
# This software is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
# See the GNU Lesser General Public License for more details.
#
# To view the GNU Lesser General Public License visit
# http://www.gnu.org/copyleft/lesser.html
# or write to the Free Software Foundation, Inc.,
# 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

# Make a BOINC installation "secure" on a Macintosh with stand-alone BOINC Client
# The BOINC installer does this for a Macintosh installation with BOINC Manager; do not use this script.
#
# Create groups and users, set file/dir ownership and protection
#
# Execute this as root in the BOINC directory
# You must have already run the installer script
# that creates the switcher/ and locale/ directories, and their contents

# In addition, you should add boinc_master and boinc_projects
# to the supplementary group list of users who will administer BOINC.
# e.g. for user mary:
# sudo dscl . -merge /groups/boinc_master users mary
# sudo dscl . -merge /groups/boinc_project users mary

function make_boinc_user() {
    # Check whether group already exists
    name=$(dscl . search /groups RecordName $1 | cut -f1 -)
    if [ "$name" = "$1" ] ; then
        gid=$(dscl . read /groups/$1 PrimaryGroupID | cut -d" " -f2 -)
    else
        # Find an unused group ID
        gid="25"
        while true; do
            name=$(dscl . search /groups PrimaryGroupID $gid | cut -f1 -)
            if [ -z "$name" ] ; then
                break
            fi
            gid=$[$gid +1]
        done
        dscl . -create /groups/$1
        dscl . -create /groups/$1 gid $gid
    fi
    
    # Check whether user already exists
    name=$(dscl . search /users RecordName $1 | cut -f1 -)
    if [ -z "$name" ] ; then

        # Is uid=gid available?
        uid=$gid
        name=$(dscl . search /users UniqueID $uid | cut -f1 -)
        if [ -n "$name" ] ; then
            # uid=gid already in use, so find an unused user ID
            uid="25"
            while true; do
                name=$(dscl . search /groups UniqueID $uid | cut -f1 -)
                if [ -z "$name" ] ; then
                    break
                fi
                uid=$[$uid +1]
            done
        fi

        dscl . -create /users/$1
        dscl . -create /users/$1 uid $uid
        dscl . -create /users/$1 shell /usr/bin/false
        dscl . -create /users/$1 home /var/empty
        dscl . -create /users/$1 gid $gid
    fi
}

function make_boinc_users() {
    make_boinc_user boinc_master
    make_boinc_user boinc_project
}

function check_login() {
    if [ `whoami` != 'root' ]
    then
        echo 'This script must be run as root'
        exit
    fi
}

# set_perm path user group perm
#   set a file or directory to the given ownership/permissions
function set_perm() {
    chown $2:$3 "$1"
    chmod $4 "$1"
}

# same, but apply to all subdirs and files
#
function set_perm_recursive() {
    chown -R $2:$3 "$1"
    chmod -R $4 "$1"
}

# same, but apply to items in the given dir
#
function set_perm_dir() {
    for file in $(ls "$1")
    do
        path="$1/${file}"
        set_perm "${path}" $2 $3 $4
    done
}

function update_nested_dirs() {
   chmod u+x,g+x,o+x "${1}"
   
   for file in $(ls "$1")
    do
	if [ -d "${1}/${file}" ] ; then
		update_nested_dirs "${1}/${file}"
	fi
    done
}

check_login

echo "Changing directory $(pwd) file ownership to user and group boinc_master - OK? (y/n)"
read line
if [ "$line" != "y" ]
then
    exit
fi

if [ ! -f "boinc" ]
then
    echo "Can't find boinc Client in directory $(pwd); exiting"
    exit
fi

make_boinc_users

set_perm_recursive . boinc_master boinc_master u+rw,g+rw,o+r-w
set_perm . boinc_master boinc_master 0775
if [ -f gui_rpc_auth.cfg ] ; then
    set_perm gui_rpc_auth.cfg boinc_master boinc_master 0660
fi

if [ -d projects ] ; then
    set_perm_recursive projects boinc_master boinc_project u+rw,g+rw,o+r-w
    set_perm projects boinc_master boinc_master 0775
    update_nested_dirs projects
fi

if [ -d slots ] ; then
    set_perm_recursive slots boinc_master boinc_project u+rw,g+rw,o+r-w
    set_perm slots boinc_master boinc_master 0775
    update_nested_dirs slots
fi

set_perm switcher/switcher boinc_project boinc_project 6551
set_perm switcher/setprojectgrp boinc_master boinc_project 2500
set_perm switcher boinc_master boinc_master 0550

set_perm_recursive locale boinc_master boinc_master u+r-w,g+r-w,o-rwx

set_perm boinc boinc_master boinc_master 6555       # boinc client
