#!/bin/ksh
#Rem
#Rem NAME
#Rem   hot_dbbackup.sh
#Rem
#Rem FUNCTION
#Rem   Generic Database Backup Wrapper Script called by Netbackup
#Rem
#Rem NOTES
#Rem   - No hard-coding of username/passwords
#Rem   - copy this script to /opt/oracle/rman/scripts/<db_name> directory
#Rem   - Create an entry for the database with DBID and STATUS and ARCH_KEEP    
#Rem
#Rem MODIFIED (MM/DD/YY)
#Rem    deep  12/04/03 - minor mods/added header
#Rem    venu  01/01/02 - Created
#Rem

. /home/oracle/.bash_profile
export PATH=$PATH:/bin
export NB_ORA_SCRIPTS=/opt/oracle/rman/scripts
export BATCH_PARAMETER=$PWD/parameters.ora

CUR_DIR=`dirname $0`
export BATCH_PARAMETER=$CUR_DIR/parameters.ora
NB_ORA_SCRIPTS=`grep -w NB_ORA_SCRIPTS $BATCH_PARAMETER |grep -v ^#| awk -F= '{ print $2}'`

export DB_NAME=`dirname $0|awk -F'/' '{print $NF}'`
export ORACLE_SID=`ps -eaf|grep -i pmon|grep -v grep | awk -F"_" '{ print $3 }' | grep -i $DB_NAME`

su oracle -c "${NB_ORA_SCRIPTS}/dbbackup.sh ${ORACLE_SID}"
