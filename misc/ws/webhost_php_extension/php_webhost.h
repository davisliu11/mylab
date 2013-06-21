#ifndef PHP_WEBHOST_H
	#define PHP_WEBHOST_H
	#define PHP_WEBHOST_EXTNAME "webhost"
	#define PHP_WEBHOST_EXTVER "1.0"

	#ifdef HAVE_CONFIG_H
		#include "config.h"
	#endif

	#include "php.h"
	extern zend_module_entry webhost_module_entry;
	#define phpext_webhost_ptr &webhost_module_entry

	PHP_RINIT_FUNCTION(webhost);
#endif
