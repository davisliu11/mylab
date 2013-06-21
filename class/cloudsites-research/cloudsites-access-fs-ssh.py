#!/usr/bin/env python

import logging

import ConfigParser
import MySQLdb

from collections import defaultdict
from errno import ENOENT
from stat import S_IFDIR, S_IFLNK, S_IFREG, S_IRUSR, S_IRGRP, S_IROTH, S_IXUSR, S_IXGRP, S_IXOTH
#from sys import argv, exit
from time import time, sleep

from fuse import FUSE, FuseOSError, Operations, LoggingMixIn

from threading import Thread

import sys
import subprocess

import socket

if not hasattr(__builtins__, 'bytes'):
	bytes = str

import os 
import inspect

###################
# SQL is for wimps:
###################
#users = {
#	'black','blue','green','red'
#}

user_domains = {
	'black': ('abc.co.nz', 'bat.co.nz'),
	'blue': ('alpha.co.nz', 'butt.co.nz', 'potatoe.co.nz'),
	'green': ('cheese.co.nz', ),
	'red': ('alpha.co.nz', 'potatoe.co.nz')
}

site_domains = {
	'1001': ('abc.co.nz', ),
	'1002': ('bat.co.nz', ),
	'1003': ('alpha.co.nz', 'butt.co.nz', 'potatoe.co.nz'),
	'1004': ('cheese.co.nz', ),
	'1005': ('alpha.co.nz', ),
	'1006': ('potatoe.co.nz', ),
}

#user_sites = {
#	'black': ('1001', '1002', '1003', '1004', '1005'),
#	'blue':	('1001', '1003'),
#	'green': ('1004', ),
#	'red': ('1001', '1005', '1006')
#}

site_ftp = {
	'1001': ('119.47.119.110', 'site1001', 'eysky8yer'),
	'1002': ('119.47.119.110', 'site1002', '123wer'),
	'1003': ('119.47.119.110', 'site1003', '123wer'),

	'1004': ('119.47.119.114', 'user2', '123wer'),
	'1005': ('119.47.119.114', 'user2', '123wer'),
	'1006': ('119.47.119.114', 'user2', '123wer'),
}
###################

site_root = '/home/ftp/'

def mysql_connect():
        # To Do: need to handle exceptions better: output them somewhere...

        config = ConfigParser.ConfigParser()
        config.readfp(open('/etc/cloudsites.conf'))
        db_host = config.get('db','db_host')
        db_name = config.get('db','db_name')
        db_ro_user = config.get('db','db_ro_user')
        db_ro_pass = config.get('db','db_ro_pass')

        try:
                connection = MySQLdb.connect(host=db_host,user=db_ro_user,passwd=db_ro_pass,db=db_name)
                cursor = connection.cursor()
        except Exception as excp:
                do_log("MySQL Exception: " + repr(excp))
                raise

        return cursor

def do_log(log):
	with open("/home/ben/fs.log", "a") as myfile:
		myfile.write(log)
		myfile.write("\n")

def whoami():
    return inspect.stack()[1][3]

def whosdaddy():
    return inspect.stack()[2][3]

def users_path(depth, prefix):
	if depth == 1:
		# lowercase a-z
		return tuple(map(chr, range(97, 123)))
	elif depth == 2:
		# 0-9 and lowercase a-z
		return tuple(map(chr, range(48, 58))) + tuple(map(chr, range(97, 123)))
	elif depth == 3:
		return user_search(prefix)
		#seen = set()
		#seen_add = seen.add
		#return tuple([ x for x in users if str(x[0:2])==prefix and x not in seen and not seen_add(x)])

	#if depth == 1:
		# Return a list of all the first letters in usernames
		#list = [x[(depth-1)] for x in users]
		# Create a unique list using a set
		#seen = set()
		#seen_add = seen.add
		#return tuple([ x for x in list if x not in seen and not seen_add(x)])

def sites_path(depth, prefix):
	if depth == 1:
		# lowercase a-z
		return tuple(map(chr, range(97, 123)))
	elif depth == 2:
		# 0-9 and lowercase a-z
		return tuple(map(chr, range(48, 58))) + tuple(map(chr, range(97, 123)))
	#elif depth == 3:
	#	seen = set()
	#	seen_add = seen.add
	#	return tuple([ x for x in users if str(x[0:2])==prefix and x not in seen and not seen_add(x)])

def fuse_mounted(dir):
	# :Steve: This is a pretty inefficient check
	proc = subprocess.Popen(["mount", "-l", "-t", "fuse"], stdout=subprocess.PIPE, shell=True)
	while True:
		line = proc.stdout.readline()
		if line != '':
			if dir == line.rstrip().split(' ')[2]:
				return True
		else:
			break
	return False

def ftp_stat(str):
	print "FTP STAT: " + str

def is_valid_user(username):
	#return username in user_domains.keys()
	# To Do: handle MySQL connections better?
	# To Do: input validation / SQL injection prevention
        cursor = mysql_connect()

	sql = "SELECT userid FROM clouduser WHERE user='%s'" % username

        try:
                cursor.execute(sql)
		return cursor.rowcount > 0
        except Exception as excp:
                do_log("MySQL Exception: " + repr(excp))
                raise

	return False

def domains_for_user(username):
	#if user_domains.has_key(username):
	#	return user_domains[username]
	
	return FuseOSError(ENOENT)

def user_search(prefix):
	# To Do: need to handle exceptions better: output them somewhere...
        cursor = mysql_connect()

	sql = "SELECT user FROM clouduser WHERE user LIKE '%s%s'" % (prefix,'%')

	try:
                cursor.execute(sql)
		if cursor.rowcount == 0:
			return ()
		else:
			# Assume only one element per tuple i.e. one user per row
                	return tuple(str(x[0]) for x in cursor.fetchall())
        except Exception as excp:
                do_log("MySQL Exception: " + repr(excp))
                raise

        return ()

def user_ssh_private_key(username):
	# To Do: need to handle exceptions better: output them somewhere...
        cursor = mysql_connect()

	sql = "SELECT private_key FROM clouduser,clouduser_privatekey WHERE clouduser.userid=clouduser_privatekey.userid AND user='%s'" % username

	try:
                cursor.execute(sql)
		if cursor.rowcount == 0:
			return ""
		else:
			return str(cursor.fetchone()[0])
        except Exception as excp:
                do_log("MySQL Exception: " + repr(excp))
                raise

        return ""

def sites_for_user(username):
	#if user_sites.has_key(username):
	#	return user_sites[username]

	# To Do: need to handle exceptions better: output them somewhere...
        cursor = mysql_connect()

	sql = "SELECT DISTINCT siteid FROM cloudsiteuser,clouduser WHERE cloudsiteuser.userid=clouduser.userid AND clouduser.user='%s'" % username
	print sql

        try:
                cursor.execute(sql)
                # Assume only one element per tuple i.e. one site per row
		return tuple(str(x[0]) for x in cursor.fetchall())
        except Exception as excp:
                do_log("MySQL Exception: " + repr(excp))
                raise

	return FuseOSError(ENOENT)

def site_owned_by_user(username, site):
	# To Do: make this more efficient?
	sites = sites_for_user(username)

	for check_site in sites:
		if check_site == site:
			return True

	return False

def backend_for_site(site):
	# To Do: need to handle exceptions better: output them somewhere...
        cursor = mysql_connect()

        sql = "SELECT cloud_backend_site.backendid,access_address FROM cloud_backend_site,cloud_backend WHERE cloud_backend_site.backendid=cloud_backend.backendid AND cloud_backend_site.siteid=%s" % site
        print sql

        try:
                cursor.execute(sql)
		return cursor.fetchone()
        except Exception as excp:
                do_log("MySQL Exception: " + repr(excp))
                raise

        return FuseOSError(ENOENT)

def uid_gid_for_user(username):
	# To Do: Input validation
	try:
		proc = subprocess.Popen(["/usr/bin/getent passwd %s" % username], stdout=subprocess.PIPE, shell=True)
		line = proc.stdout.readline()
		if line != '':
			tokens = line.rstrip().split(':')
			return (int(tokens[2]), int(tokens[3]))
	except:
		return (4000000000,4000000000)

	return (4000000000,4000000000)

def full_site_path(site):
	return os.path.join(site_root, site.strip("/"))

def splitpath(path, maxdepth=20):
	( head, tail ) = os.path.split(path)
	return splitpath(head, maxdepth - 1) + [ tail ] if maxdepth and head and head != path else [ head or tail ]

def path_to_real(path_arr):
	site = path_arr[2]
	return os.path.join(full_site_path(site), *path_arr[3:])

def stat_to_dict(path):
	filestat = os.stat(path)
	
	return dict(st_mode = filestat.st_mode, st_nlink=filestat.st_nlink, st_size=filestat.st_size, st_ctime=filestat.st_ctime, st_mtime=filestat.st_mtime, st_atime=filestat.st_atime, st_uid=filestat.st_uid, st_gid=filestat.st_gid)

def validate_user_path(path_arr):
	return is_valid_user(path_arr[1])

def validate_site_path(path_arr):
	# :Steve: updated
	try:
		path_arr[5]
	except:
		return False

	if path_arr[5] != "sites":
		return False

	return is_valid_user(path_arr[3]) and (path_arr[5] in sites_for_user(path_arr[3]))

def get_site_path_hidden(path_arr):
	return "/".join(path_arr[1:4]) + "/.sites/" + "/".join(path_arr[5:])

class SiteProxy(Operations):
	def __init__(self):
		self.ftpcache = {}
		self.count = 0
		self.calls = []

	def site_mount(self, site, path):
		path_arr = splitpath(path)
		site_path = "/".join(path_arr[1:6])
		site_path_full = full_site_path(site_path)

		site_path_hidden = get_site_path_hidden(path_arr[:6])
		site_path_full_hidden = full_site_path(site_path_hidden)

		print "Site Path: %s" % site_path
		print "Site Path Hidden: %s" % site_path_hidden

		# To Do: make sure the user owns the requested site

		full_path = full_site_path(path)
		do_log("FULL path %s" % site_path_full)
		names = []

		# To Do: get lock on doing the mount

		if not fuse_mounted(site_path_full_hidden):
			# To Do: ensure user is valid
			user = path_arr[3]

			backend_details = backend_for_site(site)
			backend_access_address = backend_details[1]	
			uid_gid = uid_gid_for_user(user)

			# Mount using FTP
			# Security: Eventually this should be mounted as the ftp user (via sudo) without the user_allow_other option
			# & required as curlftpfs process needs to wait until the current fuse transaction lock is released
			#ftp_mount_args = ["curlftpfs", "-o allow_other,nonempty,uid=%s,gid=%s" % (uid, gid), "ftp://%s:%s@%s/" % (site_ftp[site][1], password, site_ftp[site][0]), "%s &" % site_path_full]

			# Mount using SSH
			# remove & mount for new hidden dir / mirror functionality
			# To Do: set uid and gid properly
			#ssh_mount_args = [ "/bin/su %s" % user, "-c '", "sshfs", "-o PasswordAuthentication=no,ConnectTimeout=3,StrictHostKeychecking=no,allow_other,nonempty,uid=%d,gid=%d" % (uid_gid[0], uid_gid[1]), "site1001@%s:/var/www/sites/site1001 %s" % (backend_access_address, site_path_full_hidden), "'"]
			ssh_mount_args = [ "/bin/su %s" % user, "-c '", "sshfs", "-o PasswordAuthentication=no,ConnectTimeout=3,StrictHostKeychecking=no,nonempty,uid=%d,gid=%d" % (uid_gid[0], uid_gid[1]), "site1001@%s:/var/www/sites/site1001 %s" % (backend_access_address, site_path_full_hidden), "'"]
			ssh_mount_str = ' '.join(ssh_mount_args)

			print ssh_mount_str

			os.system(ssh_mount_str)

			# Background with & as the destination mount location is locked
			mount_move_args = [ "/usr/bin/sudo", "/opt/cloudsites/bin/cloudsites-access-fs-move-mount.sh %s %s" % (user, site), "&" ]
			mount_move_str = ' '.join(mount_move_args)

			print mount_move_str

			os.system(mount_move_str)

	def chmod(self, path, mode):
		do_log(whoami() + " " +path);
		path_arr = splitpath(path)
		
		if len(path_arr) > 3: 
			if validate_site_path(path_arr):
				os.chmod(path_to_real(path_arr), mode)
				
		raise FuseOSError(ENOENT)

	def chown(self, path, uid, gid):
		do_log(whoami() + " " +path);
		return #user has all access to all

	def create(self, path, mode):
		do_log(whoami() + " " +path);
		path_arr = splitpath(path)
		
		if len(path_arr) > 3: 
			if validate_site_path(path_arr):
				f = open(path_to_real(path_arr), 'wb')
				
				return f.fileno()
		
		
		raise FuseOSError(ENOENT)

	def getattr(self, path, fh=None):
		do_log(whoami() + " " +path);
		path_arr = splitpath(path)
		self.count =  self.count+1

		path_arr_len = len(path_arr)

		# Handle requests for .ssh and .ssh/*
		if path_arr_len == 5 and path_arr[4]==".ssh":
			uid_gid = uid_gid_for_user(path_arr[3])
			return dict(st_mode=(S_IFDIR | 0700), st_nlink=2, st_size=4096, st_ctime=time(), st_mtime=time(), st_atime=time(), st_uid=uid_gid[0], st_gid=uid_gid[1])
		elif path_arr_len == 6 and path_arr[4]==".ssh":
			uid_gid = uid_gid_for_user(path_arr[3])
			if (path_arr[-1]=="config"):
				return dict(st_mode=(S_IFREG | 0400), st_nlink=0, st_size=4096, st_ctime=time(), st_mtime=time(), st_atime=time(), st_uid=uid_gid[0], st_gid=uid_gid[1])
			if (path_arr[-1]=="id_rsa"):
				ssh_key = user_ssh_private_key(path_arr[3])
				ssh_key_size = len(ssh_key)
				if ssh_key == "":
					raise FuseOSError(ENOENT)
				else:
					return dict(st_mode=(S_IFREG | 0400), st_nlink=0, st_size=ssh_key_size, st_ctime=time(), st_mtime=time(), st_atime=time(), st_uid=uid_gid[0], st_gid=uid_gid[1])
			else:
				raise FuseOSError(ENOENT)
		elif path_arr_len >= 6:
			#if path_arr[4] == "sites" and validate_site_path(path_arr):
			if path_arr[4] == ".sites":
				return dict(st_mode=(S_IFDIR | 0755), st_nlink=2, st_size=4096, st_ctime=time(), st_mtime=time(), st_atime=time(), st_uid=1002, st_gid=1002)				
			elif path_arr[4] == "sites":
				site_path = "/" + "/".join(path_arr[1:(path_arr_len-1)])

				if not site_owned_by_user(path_arr[3], path_arr[5]):
					raise FuseOSError(ENOENT)

				# Return a stat of the hidden mount as a lock prevents us from doing that on the non hidden dir
				site_path_hidden = get_site_path_hidden(path_arr)
                                site_path_full_hidden = full_site_path(site_path_hidden)

				#lstat = os.lstat(site_path_full_hidden)
				return stat_to_dict(site_path_full_hidden)

		#		name = path_arr[path_arr_len-1]
		#		try:
		#			self.ftpcache[site_path]
		#			try:
		#				self.ftpcache[site_path][name]
		#				return self.ftpcache[site_path][name]
		#			except:
		#				pass
		#		except:
		#			pass
	
				#if self.ftpcache[site_path] and self.ftpcache[site_path][name]:
				#	return self.ftpcache[site_path][name]

		# ugly mount on getattr :-/ ...
		#path_arr_len = len(path_arr)
		#if path_arr_len == 6:
		#	validate_site_path(path_arr)
		#	if path_arr[4] == "sites" and validate_site_path(path_arr):
		#		if self.calls[len(self.calls)-2][0] == "getattr" and \
		#			self.calls[len(self.calls)-2][1] == "/" + "/".join(path_arr[1:5]) and \
		#			not (
		#			self.calls[len(self.calls)-3][0] == "readdir" and \
		#			self.calls[len(self.calls)-3][1] == "/" + "/".join(path_arr[1:5]) \
		#			):
		#			print "UGLY MOUNT"
		#			site_mount(path_arr[5], path)
		# end ugly mount ...

		#if len(path_arr) <= 10: # root "/"

		# To Do: return nothing instead of this dict if the directory shouldn't exist
		return dict(st_mode=(S_IFDIR | 0755), st_nlink=2, st_size=4096, st_ctime=time(), st_mtime=time(), st_atime=time(), st_uid=1002, st_gid=1002)
			
		#elif len(path_arr) == 2: 
			#if validate_user_path(path_arr):
		#	return dict(st_mode=(S_IFDIR | 0644), st_nlink=2, st_size=4096, st_ctime=time(), st_mtime=time(), st_atime=time(), st_blocks=8, st_uid=1001, st_gid=1001)
		#elif len(path_arr) >= 3: 
			#if validate_site_path(path_arr):			
		#	return stat_to_dict(path_to_real(path_arr))
		
		raise FuseOSError(ENOENT)

	def getxattr(self, path, name, position=0):
		do_log(whoami() + " " +path);
		return ''
		attrs = self.files[path].get('attrs', {})

		try:
			return attrs[name]
		except KeyError:
			return ''	   # Should return ENOATTR

	def listxattr(self, path):
		do_log(whoami() + " " +path);
		return []
		attrs = self.files[path].get('attrs', {})
		return attrs.keys()

	def mkdir(self, path, mode):	
		do_log(whoami() + " " +path);
		path_arr = splitpath(path)
		
		if len(path_arr) > 3: 
			if validate_site_path(path_arr):
				try:
					os.mkdir(path_to_real(path_arr), mode)
				except:
					raise FuseOSError(ENOENT)
				return
		
		raise FuseOSError(ENOENT)

	def open(self, path, flags):
		do_log(whoami() + " " +path);
		path_arr = splitpath(path)

		path_arr_len = len(path_arr)

                #if path_arr_len == 6 and path_arr[-1]=="config":
		#	return open("/dev/null").fileno()

                if path_arr_len == 6 and path_arr[-1]=="id_rsa":
			return open("/dev/null").fileno()
	
		if len(path_arr) > 3: 
			print "path: %s\n" % repr(path)
			if validate_site_path(path_arr):
				return open(path_to_real(path_arr)).fileno()
		
		raise FuseOSError(ENOENT)

	def read(self, path, size, offset, fh):
		do_log(whoami() + " " +path);
		path_arr = splitpath(path)

		path_arr_len = len(path_arr)

                #if path_arr_len == 6 and path_arr[-1]=="config":
		#	return "UserKnownHostsFile=/dev/null"

                if path_arr_len == 6 and path_arr[-1]=="id_rsa":
			return user_ssh_private_key(path_arr[3])
			#return site_key

		if len(path_arr) > 3: 
			if validate_site_path(path_arr):
				f = open(path_to_real(splitpath(path)))
				f.seek(offset)
				return f.read(size)
				
		raise FuseOSError(ENOENT)
	
	def opendir(self, path):
		do_log(whoami() + " " +path);
		path_arr = splitpath(path)
		path_arr_len = len(path_arr)

		if path_arr_len == 6:
			if path_arr[4] == "sites":
				do_log("TRY MOUNT HERE?")
		return 0	

	def readdir(self, path, fh):
		do_log(whoami() + " " +path);
		path_arr = splitpath(path)
		path_arr_len = len(path_arr)

		if path_arr_len <= 2:
			return ('.', '..') + users_path(path_arr_len,"")
		elif path_arr_len == 3: 
			return ('.', '..') + users_path(path_arr_len,str("".join(path_arr[1:])))
		elif path_arr_len == 4:
			# .sites is not listed as we want it to be hidden
			return ('.', '..') + ('domains', 'sites') 
		elif path_arr_len == 5:
			if path_arr[4] == "domains":
				return ('.', '..') + domains_for_user(path_arr[3])
			elif path_arr[4] == "sites":
				return ('.', '..') + sites_for_user(path_arr[3])
			elif path_arr[4] == ".sites":
				return ('.', '..') + sites_for_user(path_arr[3])
			else:
				return ('.', '..')
		elif path_arr_len >= 6:
			if path_arr[4] == "domains":
				return ('.', '..')
			elif path_arr[4] == "sites":
				if not site_owned_by_user(path_arr[3], path_arr[5]):
					raise FuseOSError(ENOENT)
				
				self.site_mount(path_arr[5], path)

				site_path = "/" + "/".join(path_arr[1:])

				site_path_hidden = get_site_path_hidden(path_arr)
				site_path_full_hidden = full_site_path(site_path_hidden)

				return ('.', '..') + tuple(os.listdir(site_path_full_hidden))
		else:
			return ('.', '..')

			#if is_valid_user("ben"):
				#return ('.', '..') + domains_for_user(path_arr[1])
				#return ('.', '..')

		#if len(path_arr) >= 3:
			#if is_valid_user(path_arr[1]):
				#if path_arr[2] in domains_for_user(path_arr[1]):
					#return ['.', '..'] + os.listdir(path_to_real(path_arr))
		#	if is_valid_user("ben"):
		#		if path_arr[2] in domains_for_user("ben"):
		#			return ['.', '..'] + os.listdir(path_to_real(path_arr))
		#		
		#	raise FuseOSError(ENOENT)
		
		#return ['.', '..'] + [x[1:] for x in self.files if x != '/']

	def readlink(self, path):
		do_log(whoami() + " " +path);
		path_arr = splitpath(path)
		
		if len(path_arr) > 3:
			if validate_site_path(path_arr):
				return os.readlink(path_to_real(path_arr))
				
		raise FuseOSError(NOENT)

	def removexattr(self, path, name):
		do_log(whoami() + " " +path);
		attrs = self.files[path].get('attrs', {})

		try:
			del attrs[name]
		except KeyError:
			pass		# Should return ENOATTR

	def rename(self, old, new):
		do_log(whoami() + " " +path);
		old_path_arr = splitpath(old)
		new_path_arr = splitpath(new)
		
		if len(old_path_arr) > 3 and len(new_path_arr) > 3:
			if validate_site_path(old_path_arr) and validate_site_path(new_path_arr):
				os.rename(path_to_real(old_path_arr), path_to_real(new_path_arr))
				return
		
		raise FuseOSError(ENOENT)

	def rmdir(self, path):
		do_log(whoami() + " " +path);
		path_arr = splitpath(path)
		
		if len(path_arr) > 3:
			if validate_site_path(path_arr):
				os.rmdir(path_to_real(path_arr))
				return
				
		raise FuseOSError(NOENT)
		

	def setxattr(self, path, name, value, options, position=0):
		do_log(whoami() + " " +path);
		# Ignore options
		attrs = {}
		attrs[name] = value

	def statfs(self, path):
		do_log(whoami() + " " +path);
		path_arr = splitpath(path)
	
		if len(path_arr) <= 2:
			return dict(f_bsize=512, f_blocks=4096, f_bavail=2048)
		elif validate_site_path(path_arr):
			fstat = os.statvfs(path_to_real(path_arr))
		
			return dict(f_bsize=fstat.f_bsize, f_blocks=fstat.f_blocks, f_bavail=fstat.f_bavail)

		raise FuseOSError(ENOENT)

	def symlink(self, target, source):
		do_log(whoami() + " " +path);
		source_arr = splitpath(source)
		#todo work out whats up with this
		print source_arr
	
		if len(source_arr) > 3:
			if validate_site_path(source_arr):
				os.symlink(target, path_to_real(source_arr))
				return
				
		raise FuseOSError(ENOENT)
		
	def truncate(self, path, length, fh=None):
		do_log(whoami() + " " +path);
		path_arr = splitpath(path)
		
		if len(path_arr) > 3:
			if validate_site_path(path_arr):
				f = open(path_to_real(path_arr), 'wb')
				f.truncate(length)
				
				return
		
		raise FuseOSError(ENOENT)

	def unlink(self, path):
		do_log(whoami() + " " +path);
		path_arr = splitpath(path)
		
		if len(path_arr) > 3:
			if validate_site_path(path_arr):
				os.unlink(path_to_real(path_arr))
				return
				
		raise FuseOSError(ENOENT) 

	def utimens(self, path, times=None):
		do_log(whoami() + " " +path);
		path_arr = splitpath(path)
		
		if len(path_arr) > 3:
			if validate_site_path(path_arr):
				now = time()
				os.utime(path_to_real(path_arr), times or (now, now))
				return
				
		raise FuseOSError(ENOENT) 

	def write(self, path, data, offset, fh):
		do_log(whoami() + " " +path);
		path_arr = splitpath(path)
	
		if len(path_arr) > 3:
			if validate_site_path(path_arr):
				f = open(path_to_real(path_arr), "a+b")
				f.seek(offset)
				f.write(data)
				return len(data)
				
		raise FuseOSError(ENOENT) 



if __name__ == '__main__':
	if len(sys.argv) != 2:
		print('usage: %s <mountpoint>' % sys.argv[0])
		sys.exit(1)

	fuse = FUSE(SiteProxy(), sys.argv[1], foreground=True, allow_other=True)
