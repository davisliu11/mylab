#!/home/yunake/opt/bin/python2.7

LOGFILE = "/tmp/sp-tomcat-restart.log"

import sys, getpass
# ugly... will find a better way to determine base path later
sys.path.append('/home/yunake/pythonlibs')
import pexpect

logfile = file (LOGFILE,"wb")

if len (sys.argv) < 2:
  sys.exit ("usage: %s cellnumber [cellnumber ...]\ne.g. python %s 001 032" % (sys.argv[0], sys.argv[0]))

hosts = sys.argv[1:]

print "\nLog file at %s" % LOGFILE
password = getpass.getpass ('Your SP LDAP password: ')

# yes, (almost) no error checking etc etc - very pooor
for host in hosts:
  # log in to M1
  ssh = pexpect.spawn ("ssh -o StrictHostKeyChecking=no chisppd%ssp1m1.prd.sp.bskyb.com" % host)
  ssh.logfile = logfile
  ssh.expect ('password:')
  ssh.sendline (password)
  i = ssh.expect (['\$ ', 'password:'])
  if i == 1: # wrong password was given!
    sys.exit ('Your password does not work!')
  print 'logged in to m1 of ' + host

  # log in to A1
  ssh.sendline ('ssh -o StrictHostKeyChecking=no a1')
  ssh.expect ('password:')
  ssh.sendline (password)
  ssh.expect ('\$ ')
  print 'logged in to a1 of ' + host

  # restart tomcat via kill and init script
  ssh.sendline ('sudo pkill -9 -U tomcat')
  # sudo requires special handling - it might not be asking for the password!
  i = ssh.expect (['password for', '\$ '])
  if i == 0: # we were asked for password
    ssh.sendline (password)
    ssh.expect ('\$ ')
  # otherwise we already got the prompt
  print 'tomcat killed'
  ssh.sendline ('sudo /etc/init.d/tomcat6 start')
  ssh.expect ('\$ ')
  print 'tomcat started'
