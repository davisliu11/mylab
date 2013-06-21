<?
DEFINE("REG_SEG_USERNAME","[a-zA-Z]([[:alnum:]\.\-]+)");

DEFINE("REG_COMMON_NAME",       "/^[a-zA-Z_][0-9a-zA-Z_]+$/");
DEFINE("REG_PASSWORD",          "/[0-9a-zA-Z_\.,]+/");
DEFINE("REG_IP",                "/^[0-9]+(\.[0-9]+){3,3}$/");
DEFINE("REG_USERNAME",          "/^[[:alnum:]_\.\-]+$/");
DEFINE("REG_AFSGROUP",          "/^".REG_SEG_USERNAME.":".REG_SEG_USERNAME."$/");
DEFINE("REG_PERM",              "/^[0-7]{3,3}$/");
DEFINE("REG_HOMEDIR",           "|^/home/([[:alnum:] ~_\.\-/]+)$|");
DEFINE("REG_INT",				"/^[0-9]+$/");
DEFINE("REG_DOMAIN",            "/^([[:alnum:]_\-]+)(\.[[:alnum:]_\-]+)+$/");
DEFINE("REG_DATE",		"/[0-9]{4,4}-[0-9]{2,2}-[0-9]{2,2}/");
DEFINE("REG_DOMAIN_LIST",       "/^([,]?([[:alnum:]_\-]+)(\.[[:alnum:]_\-]+)+)+$/");
DEFINE("REG_EMAIL",             "/^([[:alnum:]_&\.\-]+)(\@[[:alnum:]_\-]+)(\.[[:alnum:]_\-]+)+$/");
DEFINE("REG_EMAIL_LIST",        "|^([,]?[ ]*([[:alnum:]/_&\.\-]+)((\@[[:alnum:]_\-]+)(\.[[:alnum:]_\-]+)+)?)+$|");
DEFINE("REG_EMAIL_PREFIX",      "/^([[:alnum:]\._\-]+)$/");
DEFINE("REG_MD5",               "/^[0-9a-zA-Z\.]{32,32}$/");
DEFINE("REG_TEXT",              "/.*/");
?>
