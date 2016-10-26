<strong>DevBox logs parser</strong>

beta 3 (2016-10-22)

just internal tool for own use, without any external frameworks and libs. 

just one php-file 19Kb on pure php5.6, js, css.

- listing of all errors from logs folder *.log
- saving last visit date and highlight new error lines
- color highlighting of parsed JSON data, many parameters and fields
- parsing of JSON data for each line from logs
- selecting of records by day and type
- filtering by user string (exclude and include mode, pipe symbol for separating two parameters)
- removing of compiled LESS and TPL files
- checkboxes for ignoring E_NOTICE and E_DEPRECATED
- counting of error records
- parsing for dates, errors messages, error types, error sources

<img src="https://raw.githubusercontent.com/volkoleg/log/master/screenshot2.png" />

<img src="https://raw.githubusercontent.com/volkoleg/log/6552c21b65ba4e61adb13df08689d374017bf950/screenshot.png" />
