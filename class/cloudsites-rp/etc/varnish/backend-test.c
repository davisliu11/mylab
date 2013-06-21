 # Used basic outline from: http://chillibear.org/2010/05/extending-varnish-with-c-code.html




  #include <mysql/mysql.h>
  #include <libmemcached/memcached.h>
  #include <stdlib.h>
  #include <stdio.h>
  #include <ctype.h>
  #include <syslog.h>

  #define vcl_string char

static void strtolower(const char *s) {
	register char *c;
	for (c=s; *c; c++) {
		if (isupper(*c)) {
			*c = tolower(*c);
		}
	}
	return;
}

static inline int lookup_backend(vcl_string *host) {
  //memcached_st *memc;
  //memc= memcached_create(NULL);

  MYSQL *conn;
  MYSQL_RES *res;
  MYSQL_ROW row;
  char *server = "localhost";
  char *user = "varnishtest";
  char *password = "Dq77FUB8RQLm";
  char *database = "varnishtest";
  //conn = mysql_init(NULL);

  /* Connect to database */
/*
  if (!mysql_real_connect(conn, server,
	user, password, database, 0, NULL, 0)) {
		fprintf(stderr, "%s\n", mysql_error(conn));
		exit(1);
  }
  else {
	return "123";
  }
*/
}

void vcl_lookup_backend(const struct sess *sp) {
	vcl_string *host = VRT_GetHdr(sp, HDR_REQ, "\005Host:");

	strtolower(host);

	syslog(LOG_INFO, host);

	lookup_backend(host);

	//VRT_SetHdr(sp, HDR_REQ, "\021X-Steve-IP:", ip, vrt_magic_string_end);
}


int main(int argc, char ***argv) {
	if (argv[1]) {
		lookup_backend(argv[1]);
	}
}
