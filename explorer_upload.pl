#!/usr/bin/perl -w -I/share/explorer/lib

BEGIN { push(@INC,"/explorer/lib"); }
use Net::FTP;
use File::Basename;
use Cwd;
use strict;
########################################
# Explorer upload and management
# Author: Mike Scott
# Date:   07/09/2004
# Revision: v1.1
# Updates:
# 04/10/04      Filenames can be specified on the commandline
#               Any files are uploaded to ftp.sun.co.uk, no housekeeping is
#               done.
# 15/10/04      For automatic uploads, failed uploads are now tracked through
#               the "failed_upload" directories, so failed attempts will be
#               tried four times before giving up and moving the explorer
#               to "uploaded"
# 23/01/08      Fixed pattern matching issue with stage 3 deletions
#               Added "-notransfer" option
#
# Script to manage the bulk of explorers that are generated and uploaded
# to /share/explorer
# STAGE1 : Automatically compresses any uncompressed explorers
# STAGE2 : Uploads to Sun any explorers that are not in the archive directory
# STAGE3 : Prunes the archive directory so there are no more than three
#          explorers per host (host identified by hostid/hostname combo key)

my $BASEDIR="/explorer";
my $DATADIR="$BASEDIR/HostData";
my $ARCHIVEDIR="$DATADIR/uploaded";
my @SOURCEDIR=( "$DATADIR",
                "$DATADIR/failed_upload.1",
                "$DATADIR/failed_upload.2",
                "$DATADIR/failed_upload.3");
#my $SUN_IP="192.18.108.40";
#my $SUN_IP="198.232.168.117";
my $SUN_IP="192.18.110.60";

my $SKY_EMAIL="systemsadminlogs\@bskyb.com";
my $SUN_SHAREDCALLS_EMAIL="sharedcalls\@uk.sun.com";
my $UPLOAD=1; # By default, upload files

my $logText="";
$|=1;

if (scalar(@ARGV)>0 && $ARGV[0] =~ m/^-notransfer/i) {
        $UPLOAD=0;
        shift(@ARGV);
}

if (scalar(@ARGV)>0) {

        my $callref="";

        if ($ARGV[0] =~ m/^[3-9]\d{7}/) {
                # Sun call reference number
                $callref=shift(@ARGV);

                if (scalar(@ARGV)==0) {
                        &log("Callref specified, but no files to upload!. Eedjit.");
                        exit 1;
                }
                &log("Callref: $callref");
        }

        my $file;
        foreach $file (@ARGV) {
                if ( ! ( -f $file) ) {
                        &log("file \"$file\" not found - exiting");
                        exit(1);
                }
        }
#       print "FILES=@ARGV\n";
        if (&uploadFiles(@ARGV)>0) {
                &log("Sending Mail to $SKY_EMAIL, quoting $callref");
                open(SENDMAIL,"|/usr/lib/sendmail -t");
                print SENDMAIL "To: $SKY_EMAIL\n";
                print SENDMAIL "From: BskyB SysAdmins <nobody\@bskyb.com>\n";
                print SENDMAIL "Subject: $callref fileupload\n";
                print SENDMAIL "Reply-To: nobody\@nowhere.com\n\n";
                print SENDMAIL "Files have been uploaded to ftp.sun.co.uk\n\n\n";
                print SENDMAIL &getLog();
                print SENDMAIL "\n\n\nThis is an automated mail - do not reply\n";
                close(SENDMAIL);
        }
        exit(0);
}

##########################
# Supporting function - messageubog

sub log($) {
my ($message)=@_;
my ($second,$minute,$hour,$day,$month,$year,undef,undef,undef) = localtime;
$month++;
$year+=1900;
my $string=sprintf("%02d/%02d/%04d %02d:%02d:%02d %s\n",$day,$month,$year,$hour,$minute,$second,$message);
$logText.=$string;
print($string);
}

sub getLog() { return $logText; }

##########################
# Supporting function - uploadFiles

sub uploadFiles($) {
        my (@allfiles)=@_;
        my $successCount=0;

        if (scalar(@allfiles)>0) {
                &log("FTP Connecting..");
                my $ftp=Net::FTP->new("$SUN_IP",Timeout=>240) or die "Cannot connect to $SUN_IP: $!";
                &log("FTP Connected.");
                $ftp->login("anonymous","sysadmins\@bskyb.com") or die "FTP error: $!";
                &log("FTP Logged in.");
                $ftp->cwd("/europe-cores/uk/incoming") or die "FTP error: $!";
                &log("FTP Changed to /cores/uk/incoming.");
                $ftp->binary() or die "FTP error: $!";
                $ftp->hash() or die "FTP error: $!";
                &log("Binary mode selected.");
#               if (scalar(@ARGV)==0) {
#                       chdir($DATADIR);
#               }
       
                my $file="";
                foreach $file (@allfiles) {
                        my $filename=basename($file);
                        my $time=time();
                        #$filename=~s/(explorer\..*)\.(tar\.gz)/$1.$time.$2/;

                        &log("Beginning transfer of \"$filename\"");
                       
                        my $returncode=$ftp->put($file,$filename);
               
                        if (defined($returncode)) {
                                &log("upload successful.");
                                $successCount++;
                                if (scalar(@ARGV)==0) {
                                        # If there exists a directory and a tar.gz for this explorer, then we
                                        # need to ensure that we move both simultaneously, otherwise it'll try to
                                        # upload the same explorer multiple times
                                        $file =~ s/.tar.gz//;
                                        system("mv ${file}* $ARCHIVEDIR");
                                }
               
                        } else {
                       
                                # Invert the SOURCEDIR array to find the failcount
                                my %SOURCEDIR=();
                                my $count=1;
                                foreach (@SOURCEDIR) {
                                        $SOURCEDIR{$_}=$count++;
                                }

                                $count=$SOURCEDIR{getcwd()};
                                if (defined $count) {
                                        &log("$file upload NOT sucessful (attempt $count).");
                                } else {
                                        &log("$file upload NOT sucessful.");
                                }
                                &log($ftp->message());

                                my $destdir=$ARCHIVEDIR;
                                if (defined $count) {
                                        if ($count < scalar(@SOURCEDIR)) {
                                                $destdir=$SOURCEDIR[$count];
                                        }
                                        &log("Moving to $destdir");

                                        # If there exists a directory and a tar.gz for this explorer, then we
                                        # need to ensure that we move both simultaneously, otherwise it'll try to
                                        # upload the same explorer multiple times
                                        # update: We don't need to keep unpacked explorers, so we delete the
                                        # unpacked one, and move only the .tar.gz
                                        $file =~ s/.tar.gz//;
                                        system("rm -rf ${file}");
                                        system("mv ${file}.tar.gz $destdir/${file}_X.tar.gz");
                                }
                                       
                        }
               
                }
                $ftp->quit() or die "FTP error: $!";
        }
        return $successCount;
}

&log("explorer_upload starting");
###########################
# Stage 1 - Tidy up the current directory


my $file;
my $dir;
foreach $dir ( (@SOURCEDIR,$ARCHIVEDIR)) {
        &log("Stage 1 - Housekeeping in $dir");
        opendir(DATADIR,$dir) || die "Cannot read $dir: $!";
        my @allfiles=readdir(DATADIR);
        closedir(DATADIR);

        foreach $file (grep(/^explorer.*[0-9]$/,@allfiles)) {

                # Ignore the archive if the directory has been
                # modified in the last 24 hours
                my (undef,undef,undef,undef,undef,undef,undef,undef,$atime,$mtime,$ctime,undef,undef) = stat("$dir/$file");

                if (time()-$mtime > 60*60*24) {
                        # directory hasn't been modified in the last 24 hours..
       
                        &log("Unarchived file: $file");

                        # If an unarchived explorer exists, but its archived
                        # counterpart does not, then create an archive
                        if ( ! -f "$dir/$file.tar.gz" ) {
                                &log("Archiving $file...");
                                chdir($dir);
                                system("tar cf $file.tar $file");
                                system("/usr/bin/gzip $file.tar");
                        }

                        # Delete any unarchived explorers that have archived
                        # counterparts
                        if ( -f "$dir/$file.tar.gz" ) {
                                &log("Deleting $file...");
                                system("rm -rf $dir/$file");
                        }
                }
        }
}


#######################
# Stage 2 - FTP upload to Sun

if ($UPLOAD == 1) {
        foreach my $folder (reverse @SOURCEDIR) {
                &log("Stage 2 - FTP upload - $folder");
                if (! (-d $folder)) { &log("Making $folder"); mkdir($folder); }

                opendir(DATADIR,$folder) || die "Cannot read $folder: $!";
                my @allfiles=grep(/^explorer.*gz$/,readdir(DATADIR));
                closedir(DATADIR);

                chdir($folder);
                &uploadFiles(@allfiles);
        }
} else {
        foreach my $folder (reverse @SOURCEDIR) {
                &log("Stage 2 - SKIPPING FTP upload - $folder");
        }
}



##################################
# Stage 3 - Prune the archive directory

# Current policy is to retain the first explorer seen
# and also the two most recent.

&log("Stage 3 - selectively discard old explorer files");

opendir(ARCHIVEDIR,$ARCHIVEDIR) || die "Cannot read $ARCHIVEDIR: $!";
my @allfiles=sort readdir(ARCHIVEDIR);
closedir(ARCHIVEDIR);

push(@allfiles,"explorer.dummy.dummy.0000.00.00.00.00.tar.gz");
my @filelist=();
my $match="";
my $count=scalar(@allfiles)-1;
foreach (@allfiles) {
        $count--;
        if (/explorer\.(.*)\.(.*)-(\d\d\d\d)\.(\d\d)\.(\d\d)\.(\d\d)\.(\d\d)[_X]*\.tar.gz/)  {
                $file=$_;
                # Generate a fileid, using both the hostname and hostid
                # to uniquely ident all explorers from a given host
                my $fileid="$1.$2";
#       print "$count checking $fileid : $file\n";
                if ($fileid ne $match || $count==0) {
                        if ($count==0) {
                                push(@filelist,$file);
                        }
                        # The current file is not in the set we
                        # are tracking - so time to process.

                        if (scalar(@filelist)>0) {
#print "OK - @filelist\n";
                                # Select all explorers inbetween the first and last
                                # ones to delete
                                my $start=1;
                                my $finish=scalar(@filelist)-2;

                                if ($finish > $start ) {
                                        for (my $i=$start;$i<$finish;$i++) {
                                                &log("Pruning $filelist[$i]");
                                                chdir($ARCHIVEDIR);
                                                unlink($filelist[$i]);
                                        }
                                }
                        }

                        $match=$fileid;
                        @filelist=();
                }
                push(@filelist,$file);
        }
}



&log("Sending notification mail to $SKY_EMAIL");
open(SENDMAIL,"|/usr/lib/sendmail -t");
print SENDMAIL "To: $SKY_EMAIL\n";
print SENDMAIL "From: BskyB SysAdmins <nobody\@bskyb.com>\n";
print SENDMAIL "Subject: explorer auto-upload\n";
print SENDMAIL "Reply-To: nobody\@nowhere.com\n\n";
print SENDMAIL &getLog();
print SENDMAIL "\n\n\nThis is an automated mail - do not reply\n";
close(SENDMAIL);
&log("explorer_upload finished");
