#!/usr/bin/env python

import ConfigParser
import MySQLdb

import os, stat, errno
# pull in some spaghetti to make this stuff work without fuse-py being installed
try:
    import _find_fuse_parts
except ImportError:
    pass
import fuse
from fuse import Fuse

if not hasattr(fuse, '__version__'):
    raise RuntimeError, \
        "your fuse-py doesn't know of fuse.__version__, probably it's too old."

fuse.fuse_python_api = (0, 2)

site_user_prefix = 'site'

site_root = '/home/ssh-auth-key-fs/'

def do_log(entry):
	with open("/tmp/fs.log", "a") as myfile:
                myfile.write(entry)
                myfile.write("\n")

def mysql_connect():
	# To Do: need to handle exceptions better: output them somewhere...

        config = ConfigParser.ConfigParser()
        config.readfp(open('/etc/cloudsites.conf'))
        db_host = config.get('db','db_host')
        db_name = config.get('db','db_name')
        db_rw_user = config.get('db','db_rw_user')
        db_rw_pass = config.get('db','db_rw_pass')

	try:
		connection = MySQLdb.connect(host=db_host,user=db_rw_user,passwd=db_rw_pass,db=db_name)
		cursor = connection.cursor()
	except Exception as excp:
                do_log("MySQL Exception: " + repr(excp))
                raise

	return cursor

def is_valid_site(site):
	# To Do: make sure site is only [0-9]
	#site = re.sub('[^0-9]', '', site)

	# To Do: make this nicer and include the webdrive user?
	if (site=="root"):
		return True

	cursor = mysql_connect()

	sql = "SELECT siteid FROM site WHERE siteid=%s" % (site.replace(site_user_prefix,''))
	do_log(sql)
	try:
		cursor.execute(sql)
		return cursor.rowcount > 0
	except Exception as excp:
                do_log("MySQL Exception: " + repr(excp))

	return False

def get_site_keys(site):
	if not is_valid_site(site):
		return "";

	# To Do:
	if (site=="root"):
		# To Do: handle root ssh keys in a better way... and add the webdrive user?
		f = open('/root/.ssh/authorized_keys', 'r')
		return f.read()

	# To Do: need to handle exceptions better: output them somewhere...
	cursor = mysql_connect()

	sql = "SELECT public_key FROM clouduser,cloudsiteuser WHERE clouduser.userid=cloudsiteuser.userid AND siteid=%s" % (site.replace(site_user_prefix,''))
	try:
		cursor.execute(sql)
		# Assume only one element per tuple i.e. one public_key per clouduser
		return '\n'.join(x[0] for x in cursor.fetchall())
        except Exception as excp:
		do_log("MySQL Exception: " + repr(excp))
                raise

	return ""

def site_list():
	# To Do: need to handle exceptions better: output them somewhere...
        cursor = mysql_connect()

	sql = "SELECT CONCAT('%s',siteid) FROM site ORDER BY siteid" % site_user_prefix
        try:
		cursor.execute(sql)
		# Only one element per tuple i.e. (one field from the SQL)
		#return ['root', ] + list(str(x[0]) for x in cursor.fetchall())
		return list(str(x[0]) for x in cursor.fetchall())
        except Exception as excp:
		do_log("MySQL Exception: " + repr(excp))
                raise

	return list()

class MyStat(fuse.Stat):
    def __init__(self):
        self.st_mode = 0
        self.st_ino = 0
        self.st_dev = 0
        self.st_nlink = 0
        self.st_uid = 0
        self.st_gid = 0
        self.st_size = 0
        self.st_atime = 0
        self.st_mtime = 0
        self.st_ctime = 0

class HelloFS(Fuse):
    def getattr(self, path):
        st = MyStat()
        if path == '/':
            st.st_mode = stat.S_IFDIR | 0711
            st.st_nlink = 2
	elif not is_valid_site(path[1:]):
                return -errno.ENOENT
        else:
            st.st_mode = stat.S_IFREG | 0400
            st.st_nlink = 1
	    # To Do: set the size to be accurate as this affects the keys found
	    # 		(or just set it arbitrarily high?)
            st.st_size = 1048576
            # To Do: handle root login in a better way and support the webdrive user
            if (path[1:]=="root"):
                do_log("root")
	    	st.st_uid = 0
                st.st_gid = 0
            else:
	    	st.st_uid = int(path[1:].replace('site',''))
                st.st_gid = int(path[1:].replace('site',''))

        return st

    def readdir(self, path, offset):
	for r in ['.', '..'] + site_list():
            yield fuse.Direntry(r)

    def open(self, path, flags):
	if not is_valid_site(path[1:]):
		return -errno.ENOENT
	accmode = os.O_RDONLY | os.O_WRONLY | os.O_RDWR
	if (flags & accmode) != os.O_RDONLY:
		return -errno.EACCES

    def read(self, path, size, offset):
	if not is_valid_site(path[1:]):
		return -errno.ENOENT
	keystr = get_site_keys(path[1:])
	slen = len(keystr)
        if offset < slen:
            if offset + size > slen:
                size = slen - offset
            buf = keystr[offset:offset+size]
        else:
            buf = ''
        return buf

def main():
    usage="""
Userspace SSH authentication key filesystem

""" + Fuse.fusage
    server = HelloFS(version="%prog " + fuse.__version__,
                     usage=usage,
                     dash_s_do='setsingle')

    server.allow_other = True

    server.parse(errex=1)
    server.main()

if __name__ == '__main__':
    main()
