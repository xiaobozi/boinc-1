// Berkeley Open Infrastructure for Network Computing
// http://boinc.berkeley.edu
// Copyright (C) 2005 University of California
//
// This is free software; you can redistribute it and/or
// modify it under the terms of the GNU Lesser General Public
// License as published by the Free Software Foundation;
// either version 2.1 of the License, or (at your option) any later version.
//
// This software is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
// See the GNU Lesser General Public License for more details.
//
// To view the GNU Lesser General Public License visit
// http://www.gnu.org/copyleft/lesser.html
// or write to the Free Software Foundation, Inc.,
// 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA

#ifndef H_VALIDATE_UTIL
#define H_VALIDATE_UTIL

#include <vector>
#include <string>

#include "boinc_db.h"
#include "parse.h"

// bit of a misnomer - this actually taken from the <file_ref> elements
// of result.xml_doc_in
//
struct FILE_INFO {
    std::string name;
    std::string path;
    bool optional;
    bool no_validate;

    int parse(XML_PARSER&);
};

extern int get_output_file_info(RESULT& result, FILE_INFO&);
extern int get_output_file_infos(RESULT& result, std::vector<FILE_INFO>&);
extern int get_output_file_path(RESULT& result, std::string&);
extern int get_output_file_paths(RESULT& result, std::vector<std::string>&);
extern int get_logical_name(
    RESULT& result, std::string& path, std::string& name
);

extern double median_mean_credit(WORKUNIT&, std::vector<RESULT>& results);
extern double get_credit_from_wu(WORKUNIT&, std::vector<RESULT>& results);
extern double stddev_credit(WORKUNIT&, std::vector<RESULT>& results);
extern double two_credit(WORKUNIT&, std::vector<RESULT>& results);
extern int update_credit_per_cpu_sec(
    double credit, double cpu_time, double& credit_per_cpu_sec
);
#endif
