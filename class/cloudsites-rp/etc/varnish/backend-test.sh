#!/bin/sh

cat webhost-backend.vcl | sed 's|C{||' | sed 's|}C||' | grep -v ^# > backend-test.c
echo 'int main(int argc, char ***argv) {
	if (argv[1]) {
		lookup_backend(argv[1]);
	}
}' >> backend-test.c

gcc -o do-backend-test $(mysql_config --cflags) backend-test.c $(mysql_config --libs)
