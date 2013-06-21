#include "php_webhost.h"

int __webhost_pathinfo_ovrd__() {
	// Override multiple internal functions with new ones written in PHP code
	int ret;

	// the current function name and what it will be renamed to
	char* orig_fname_pathinfo = "pathinfo";
	char* new_fname_pathinfo = "wh_pathinfo_original";

	// arguments and code for the function which will replace the original function
	char* ovrd_function_name_pathinfo = "__ovrd_pathinfo_function__";
	char* ovrd_function_args_pathinfo = "$string,$int=NULL";
	char* ovrd_function_code_pathinfo = "\
if ($int==NULL) $ret = wh_pathinfo_original($string);\
else $ret = wh_pathinfo_original($string,$int);\
\
if ($int==PATHINFO_DIRNAME) $ret = preg_replace('|^(/home)[0-9]+(/?)|','$1$2',$ret);\
else if (is_array($ret) && isset($ret['dirname'])) {\
	$ret['dirname'] = preg_replace('|^(/home)[0-9]+(/?)|','$1$2',$ret['dirname']);\
	return $ret;\
}\
\
return $ret;";

	ret = __func_mv__(orig_fname_pathinfo, new_fname_pathinfo);

	if (ret==0) return 0;

	ret = __func_ovrd__(orig_fname_pathinfo, ovrd_function_name_pathinfo, ovrd_function_args_pathinfo, ovrd_function_code_pathinfo);

	return ret;
}

int __webhost_realpath_ovrd__() {
	// Override multiple internal functions with new ones written in PHP code
	int ret;

	// the current function name and what it will be renamed to
	char* orig_fname_realpath = "realpath";
	char* new_fname_realpath = "wh_realpath_original";

	// arguments and code for the function which will replace the original function
	char* ovrd_function_name_realpath = "__ovrd_realpath_function__";
	char* ovrd_function_args_realpath = "$string";
	char* ovrd_function_code_realpath = "$info = pathinfo($string); if (is_dir($string)) return $info['dirname'].'/'.$info['basename']; else return $info['dirname'];";

	// :Steve: warning, running __func_mv__ twice causes a Suhosin "canary mismatch on efree" error
	// for machines that have the Suhosin patch
	//ret = __func_mv__(orig_fname_realpath, new_fname_realpath);
	ret = __func_ovrd__(orig_fname_realpath, ovrd_function_name_realpath, ovrd_function_args_realpath, ovrd_function_code_realpath);

	return ret;
}

int __webhost_getcwd_ovrd__() {
	// Override multiple internal functions with new ones written in PHP code
	int ret;

	// the current function name and what it will be renamed to
	char* orig_fname_getcwd = "getcwd";

	// arguments and code for the function which will replace the original function
	char* ovrd_function_name_getcwd = "__ovrd_getcwd_function__";
	char* ovrd_function_args_getcwd = "";
	char* ovrd_function_code_getcwd = "return realpath(exec('pwd'));";

	ret = __func_ovrd__(orig_fname_getcwd, ovrd_function_name_getcwd, ovrd_function_args_getcwd, ovrd_function_code_getcwd);

	return ret;
}

int __func_mv__(char* orig_fname, char* new_fname) {
	// Renames a function and inserts a new one in its place
	zend_function *func, *dummy_func;

	// Rename the function
	if(zend_hash_find(EG(function_table), orig_fname,
					  strlen(orig_fname) + 1, (void **) &func) == FAILURE)
		{
			zend_error(E_WARNING, "%s(%s, %s) failed: %s does not exist!",
					   get_active_function_name(TSRMLS_C),
					   orig_fname,  new_fname,
					   orig_fname);
			return 0;
		}
	if(zend_hash_find(EG(function_table), new_fname,
					  strlen(new_fname) + 1, (void **) &dummy_func) == SUCCESS)
		{
			zend_error(E_WARNING, "%s(%s, %s) failed: %s already exists!",
					   get_active_function_name(TSRMLS_C),
					   orig_fname,  new_fname,
					   new_fname);
			return 0;
		}
	if(zend_hash_add(EG(function_table), new_fname,
					 strlen(new_fname) + 1, func, sizeof(zend_function),
					 NULL) == FAILURE)
		{
			zend_error(E_WARNING, "%s() failed to insert %s into EG(function_table)",
					   get_active_function_name(TSRMLS_C),
					   new_fname);
			return 0;
		}
	if(zend_hash_del(EG(function_table), orig_fname,
					 strlen(orig_fname) + 1) == FAILURE)
		{
			zend_error(E_WARNING, "%s() failed to remove %s from function table",
					   get_active_function_name(TSRMLS_C),
					   orig_fname);
			zend_hash_del(EG(function_table), new_fname,
						  strlen(new_fname) + 1);
			return 0;
		}

	return 1;
	// =====
}

int __func_ovrd__(char* orig_fname, char* ovrd_function_name, char* ovrd_function_args, char* ovrd_function_code) {
	char *eval_code, *eval_name, *temp_function_name;
	int eval_code_length, retval, temp_function_name_length;

	// Define the replacement function as a PHP function
	eval_code_length = sizeof("function ") 
		+ strlen(ovrd_function_name)
		+ strlen(ovrd_function_args)
		+ 2 /* parentheses */
		+ 2 /* curlies */
		+ strlen(ovrd_function_code);
	eval_code = (char *) emalloc(eval_code_length);
	sprintf(eval_code, "function %s(%s){%s}",
			ovrd_function_name, ovrd_function_args, ovrd_function_code);
	eval_name = zend_make_compiled_string_description("webhost override function" TSRMLS_CC);
	retval = zend_eval_string(eval_code, NULL, eval_name TSRMLS_CC);

	efree(eval_code);
	efree(eval_name);
	// =====

	// Load the new realpath function in the function table
	if (retval == SUCCESS) {
		zend_function *func;

		if (zend_hash_find(EG(function_table), ovrd_function_name,
						   strlen(ovrd_function_name) + 1, (void **) &func) == FAILURE)
			{
				zend_error(E_ERROR, "%s() temporary function name not present in global function_table", get_active_function_name(TSRMLS_C));
				return 0;
			}
		function_add_ref(func);
		zend_hash_del(EG(function_table), orig_fname,
					  strlen(orig_fname) + 1);
		if(zend_hash_add(EG(function_table), orig_fname,
						 strlen(orig_fname) + 1, func, sizeof(zend_function),
						 NULL) == FAILURE)
			{
				return 0;
			}
	}
	else {
		return 0;
	}
	// =====

	return 1;
}

// function that Zend calls at the start of each request
PHP_RINIT_FUNCTION(webhost)
{
	__webhost_pathinfo_ovrd__();
	__webhost_realpath_ovrd__();
	__webhost_getcwd_ovrd__();
	return SUCCESS;
}

// Define a function that will be accessible via PHP
// This isn't strictly nessesary.  It is placeholder in-case we want
// to define our own PHP functions in the future.
PHP_FUNCTION(__webhost_function_ovrd_active__) {
	RETURN_TRUE;
}

static function_entry php_webhost_functions[] = {
        PHP_FE(__webhost_function_ovrd_active__,NULL)
        {NULL,NULL,NULL}
};

zend_module_entry webhost_module_entry = {
      #if ZEND_MODULE_API_NO >= 20010901
        STANDARD_MODULE_HEADER,        // Roughly means if PHP Version > 4.2.0
      #endif
        PHP_WEBHOST_EXTNAME,		// Define PHP extension name
	php_webhost_functions,		/* functions */
        NULL,				/* MINIT */
        NULL,				/* MSHUTDOWN */
        PHP_RINIT(webhost),		/* RINIT */
        NULL,				/* RSHUTDOWN */
        NULL,				/* MINFO */
      #if ZEND_MODULE_API_NO >= 20010901
        PHP_WEBHOST_EXTVER,		// Roughly means if PHP Version > 4.2.0
      #endif
        STANDARD_MODULE_PROPERTIES
};

#ifdef COMPILE_DL_WEBHOST
	ZEND_GET_MODULE(webhost)      // Common for all PHP extensions which are build as shared modules
#endif
