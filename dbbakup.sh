#!/bin/ksh
#Rem
#Rem NAME
#Rem   dbbackup.sh
#Rem
#Rem FUNCTION
#Rem   Generic Database Backup Script
#Rem
#Rem NOTES
#Rem   - No hard-coding of username/passwords
#Rem   - Create an entry for the database with DBID and STATUS and ARCH_KEEP    
#Rem
#Rem MODIFIED (MM/DD/YY)
#Rem    deep  06/13/07 - obseleted prev logs concept and full recovery concept as we no longer use this
#Rem    deep  03/13/07 - added suport for any number of nodes in RAC configuration [obseleted RAC_NUMBER_OF_NODES]
#Rem    deep  01/07/04 - added backup controlfile trace
#Rem    deep  12/15/03 - added support for RAC CHANNEL INFO/options
#Rem    deep  12/15/03 - added support for mltiple RAC specs [RAC_NUMBER_OF_NODES] - 2
#Rem    deep  12/15/03 - removed multiple arch backup dest and added support for CFS
#Rem    deep  12/04/03 - Major Revision - obselete parameters
#Rem    deep  10/28/03 - Added Starting and Ending pointers for easy identification of disk bkp pieces
#Rem    deep  10/23/03 - Check whether this script was fired standalone or by NB as root
#Rem    deep  10/22/03 - generic code to support Sun and Linux platforms
#Rem    deep  10/22/03 - fixed bug to read correct SID related parameters.ora
#Rem    deep  09/21/03 - Enabled support for SID Specific parameters.ora
#Rem    deep  09/02/03 - added backward support for 8i databases <compatability check>
#Rem    deep  08/21/03 - Fixed bug in dynamic Channel allocation <linux specific>
#Rem    deep  08/14/03 - Added Dynamic parameter to choose how many channels to use
#Rem    deep  07/15/03 - Added new parameters.ora to make script generic. Removed Hard Codings
#Rem    deep  07/10/03 - Dynaically decide whether to back to Tape or Disk
#Rem    deep  12/12/02 - Added ltrim for pulling dbid's as per suggestion from lalita
#Rem    deep  10/16/02 - Added support for Oracle9i RAC & Archiving old repository information in history tables
#Rem    deep  09/17/02 - Added extra code validations & Re-formatted and added header & additional comments
#Rem    venu  01/01/02 - Created
#Rem

############################################
# Define parameter file     & Global Variables #
############################################

USAGE="\nUsage $0 <SID> <Inclevel>\n"
if [ $# -lt 1 ]
then
  echo $USAGE
  exit
else
  export ORACLE_SID=$1
fi

if [ "${BATCH_PARAMETER}" = "" ]; then
  echo "Setting parameters.ora during execution of Script $0 ..."
  CUR_DIR=`dirname $0`
  export BATCH_PARAMETER=$CUR_DIR/${ORACLE_SID}/parameters.ora
  #export BATCH_PARAMETER=`find ${CUR_DIR} -name "${ORACLE_SID}.parameters.ora" -print`
  echo "Using parameter file --> ${BATCH_PARAMETER}"
  LOCAL_INVOKE="YES"
else
  LOCAL_INVOKE="NO"
fi

export ORATAB=`grep -w ORATAB $BATCH_PARAMETER |grep -v ^#| awk -F= '{ print $2}'`
export NB_ORA_SCRIPTS=`grep -w NB_ORA_SCRIPTS $BATCH_PARAMETER |grep -v ^#| awk -F= '{ print $2}'`

export logdir=${NB_ORA_SCRIPTS}/log
export tmpfile=$logdir/${ORACLE_SID}_CF_EnqCheck.tmp
export logfile=$logdir/${ORACLE_SID}_dbbackup_`date +%d`.log
export ABN_NB_CONF_FILE=$NB_ORA_SCRIPTS/hostmap.DoNotDelete

mkdir -p $logdir

> $logfile

/bin/cat ${NB_ORA_SCRIPTS}/banner.dat                >> $logfile
echo "\nScript: $0\n"                         >> $logfile

grep -i "^$ORACLE_SID:" $ORATAB > /dev/null 2>&1
if [ $? != 0 ]; then
   print "\n$ORACLE_SID is not valid (check $ORATAB)\n"
   print "\n$ORACLE_SID is not valid (check $ORATAB)\n" >> $logfile
   print $USAGE                              >> $logfile
   print $USAGE
   exit
fi

#################################################
#Check where this script was fired from      #
#################################################
if [ "${LOCAL_INVOKE}" = "YES" ]; then
  echo "\nSetting parameters.ora during execution of Script $0 ...\n"      >> $logfile
  echo "Backup was fired manually using Script $0 \n"                >> $logfile
else
  echo "\nBackup was fired using Script $0 automatically by master scheduler running as root ...\n"      >> $logfile
fi

echo "\nUsing parameter file --> ${BATCH_PARAMETER}\n" >> $logfile



#################################################
#Check input parameters - PARA_BACKUP_DEVICE     #
#################################################

export PARA_BACKUP_DEVICE=`grep -w PARA_BACKUP_DEVICE $BATCH_PARAMETER |grep -v ^#| awk -F= '{ print $2}'`
if [ "$PARA_BACKUP_DEVICE" = "TAPE" ]; then
        echo "\nPreparing for Tape backup"       
        echo "\nPreparing for Tape backup"        >> $logfile
elif [ "$PARA_BACKUP_DEVICE" = "DISK" ]; then
        echo "\nPreparing for Disk backup"       
        echo "\nPreparing for Disk backup"        >> $logfile

     export DB_BACKUP_LOC=`grep -w DB_BACKUP_LOC $BATCH_PARAMETER |grep -v ^#| awk -F= '{ print $2}'`
     if [ ! -d ${DB_BACKUP_LOC} ];then
          echo "Invalid directory specified for DB_BACKUP_LOC. Check parameters.ora.. aborting.."
          echo "Invalid directory specified for DB_BACKUP_LOC. Check parameters.ora.. aborting.."     >> $logfile
       exit
     else
          echo "\nDisk backup location set to $DB_BACKUP_LOC"       
          echo "\nDisk backup location set to $DB_BACKUP_LOC"               >> $logfile
     fi
else
        echo "Invalid Value for PARA_BACKUP_DEVICE. Check $BATCH_PARAMETER"        >> $logfile
        echo "Invalid Value for PARA_BACKUP_DEVICE. Check $BATCH_PARAMETER"
        exit
fi

#################################################
#Check input parameters - PARA_BACKUP_LEVEL     #
#################################################

export PARA_BACKUP_LEVEL=`grep -w $(date +%a) $BATCH_PARAMETER |grep -v ^#| awk -F= '{ print $2}'`
if [ "$PARA_BACKUP_LEVEL" = "0" ]; then
        echo "\nPreparing for FULL backup"     >> $logfile
        echo "\nPreparing for FULL backup"
elif [ "$PARA_BACKUP_LEVEL" = "1" ]; then
        echo "\nPreparing for Incr backup"     >> $logfile
        echo "\nPreparing for Incr backup"       
else
        echo "Invalid Value for PARA_BACKUP_LEVEL. Check $BATCH_PARAMETER"     >> $logfile
        echo "Invalid Value for PARA_BACKUP_LEVEL. Check $BATCH_PARAMETER"
        exit
fi

#########################################
#Check input parameters - FILES_PER_SET     #
#########################################

export FILES_PER_SET=`grep -w FILES_PER_SET $BATCH_PARAMETER |grep -v ^#| awk -F= '{ print $2}'`
if [ "$FILES_PER_SET" = "1" ]; then
     FILES_PER_SET_VAL="FILESPERSET 1"
        echo "\nUsing FILES_PER_SET=1"               >> $logfile
        echo "\nUsing FILES_PER_SET=1"                           
elif [ "$FILES_PER_SET" = "DEFAULT" ]; then
     FILES_PER_SET_VAL=""
        echo "\nUsing Default value for FILES_PER_SET"     >> $logfile
        echo "\nUsing Default value for FILES_PER_SET"                   
else
        echo "Invalid Value for FILES_PER_SET. Check $BATCH_PARAMETER"   >> $logfile
        echo "Invalid Value for FILES_PER_SET. Check $BATCH_PARAMETER"
        exit
fi



#################################################
# Start other tasks like setting oracle env.     #         
#################################################
export ORACLE_HOME=`grep ^${ORACLE_SID}: ${ORATAB} | awk -F: '{print $2}'`
export PATH=${ORACLE_HOME}/bin:/bin:/usr/local/bin:/sbin:/usr/bin:$PATH
export LD_LIBRARY_PATH=${ORACLE_HOME}/lib:/usr/ucblib
export SYS_NAME=`hostname`


#if [ "$PARA_BACKUP_DEVICE" = "TAPE" ]; then
#this code is is disabled by giving TAPE_DISABLED
if [ "$PARA_BACKUP_DEVICE" = "TAPE_DISABLED" ]; then
     echo "Attempting to Configure NetBackUp Environment"          >> $logfile
     echo "Attempting to Configure NetBackUp Environment"         
     export DBID=`sqlplus -s <<EOF
     /
     set heading off feedback off term off trimspool on pages 0
     select ltrim(DBID) from v\\$database;
EOF`
     export NB_ORA_CLIENT=`cat $ABN_NB_CONF_FILE| grep -i ^$DBID | awk -F: '{print $2}'`
     export NB_ORA_SERVER=`cat $ABN_NB_CONF_FILE| grep -i ^$DBID | awk -F: '{print $3}'`
    
     if [ "$NB_ORA_CLIENT" = "" ]; then
                echo "Unable to set NB Client! Check Hostmap!!"      >> $logfile
             exit
     fi
     if [ "$NB_ORA_SERVER" = "" ]; then
             echo "Unable to set NB Server! Check Hostmap!!"          >> $logfile
             exit
     fi

if [ "${NB_ORA_CINC}" = "" ];     then
     export NB_ORA_CINC=0
fi

#this code is is disabled by giving TAPE_DISABLED above and below
#else
#     echo "\nSkipping Configure for NB As this is DISK"          >> $logfile
#     echo "Skipping Configure for NB As this is DISK"    
fi

export FLAG=`${NB_ORA_SCRIPTS}/check_flag.sh ${ORACLE_SID}`
if [ "$FLAG" = "X" ]
then
  echo "Could not Connect to Repository. Exiting .."
  echo "\nCould not Connect to Repository. Exiting .." >> $logfile
  exit
fi
if [ "$FLAG" = "N" ]
then
  echo "\nBackup for this database is disabled. Exiting .." >> $logfile
  echo "Backup for this database is disabled. Exiting .."
  exit
fi

# Proceed as Status is "Y" at this point ..

#check for CF Enqueue - RMAN Bug Condition
echo "\nSearching for Enqueue Conflicts ..." >> $logfile
$ORACLE_HOME/bin/sqlplus -s / << EOF > $tmpfile
set feedback on
set echo off timing off hea on feedback on linesize 120
select s.sid, p.spid, s.username,s.program, s.module, s.action, s.logon_time,l.*
from gv\$session s, gv\$enqueue_lock l, gv\$process p
where l.sid = s.sid
and l.type = 'CF'
and l.id1 = 0
and l.id2 = 2
and p.addr=s.paddr;
exit
EOF
egrep "no rows selected" $tmpfile > /dev/null 2>&1
if [ $? = 1 ]; then
   cat $tmpfile >> $logfile
   echo "Control File Enqueue Lock Detected - Cannot Contine RMAN Backup .. " >> $logfile
   exit
fi
rm $tmpfile > /dev/null 2>&1

#search for any running backups on this or other instances
echo "\nSearching if other RMAN jobs are running ..." >> $logfile
export tmpfile=$logdir/${ORACLE_SID}_MultiRMANCheck.tmp
$ORACLE_HOME/bin/sqlplus -s / << EOF > $tmpfile
set echo off timing off hea on feedback on linesize 120
col CLIENT_INFO for a28
col USERNAME for a15
SELECT p.INST_ID,sid, s.serial#, s.username, spid, client_info
FROM gv\$process p, gv\$session s
WHERE p.addr = s.paddr
and p.INST_ID=s.inst_id
AND client_info LIKE '%id=rman%';
exit
EOF
egrep "no rows selected" $tmpfile > /dev/null 2>&1
if [ $? = 1 ]; then
   cat $tmpfile >> $logfile
   echo "Backup running for this DB - Pls retry after it completes .. " >> $logfile
   exit
fi
rm $tmpfile > /dev/null 2>&1




{

export START_DATE=`date "+%Y/%m/%d %H:%M:%S"`

if [ "$PARA_BACKUP_DEVICE" = "TAPE" ]; then
    
     echo   "hostmap.doNotDelete has been deleted. Ignore NB_ORA-Client / NB_ORA_SERVER"
     echo   "NB_ORA_CLIENT set to $NB_ORA_CLIENT"
     echo   "NB_ORA_SERVER set to $NB_ORA_SERVER"
    
     echo   "NB_ORA_FULL: $NB_ORA_FULL"
     echo   "NB_ORA_INCR: $NB_ORA_INCR"
     echo   "NB_ORA_CINC: $NB_ORA_CINC"
     echo   "NB_ORA_SERV: $NB_ORA_SERV"
     echo   "NB_ORA_CLASS: $NB_ORA_CLASS"
     echo   "NB_ORA_PC_SCHED: $NB_ORA_PC_SCHED"
     echo   "NB_ORA_SCHEDULED: $NB_ORA_SCHEDULED"
     echo   "NB_ORA_USER_INITIATED: $NB_ORA_USER_INITIATED"
else
     echo   "\nSkipped NB Var Display Section As this is DISK\n"
fi

echo   "\nGathering Priv RMAN info from Catalog Repository ...\n"

connstrcat=`sqlplus -s <<EOF
/
set heading off
set feedback off
set term off
set trimspool on
set pages 0

select connstr from backup_setup_new;
EOF`

export ctltag="${ORACLE_SID}_ctrl"

if [ "$PARA_BACKUP_DEVICE" = "TAPE" ]; then
  export fmcl="cl_%d_%T_%U"
  export fm="dbbkp_%d_%T_%U"
  export DEVICE_TYPE="'sbt_tape'"
else
  export fmcl="$DB_BACKUP_LOC/cl_%d_%T_%U"
  export fm="$DB_BACKUP_LOC/dbbkp_%d_%T_%U"
  export DEVICE_TYPE="disk"
fi

#NB_ORA_CINC is used if FULL/Inc is defined on NB Class
#Since we use parameters.ora, this parameter is overriddeb by $PARA_BACKUP_LEVEL
#if [ "${NB_ORA_CINC}" = "1" ]

if [ "${PARA_BACKUP_LEVEL}" = "1" ];then
  export dbtag="${ORACLE_SID}_Cumul_backup"
  export backup_level="1 cumulative"
  export BACKUP_TYPE="Cumulative Incremental"
else # default Full Backup
  export dbtag="${ORACLE_SID}_Full_backup"
  export backup_level="0"
  export BACKUP_TYPE="FULL"
fi


sqlplus -s /nolog<<EOF
connect /
alter session set nls_date_format='YYYY/MM/DD HH24:MI:SS';
declare
  ndbid number;
  dbname varchar2(20);
begin
  select ltrim(dbid), name into ndbid, dbname from v\$database;
  insert into BACKUP_LOG_HISTORY select * from BACKUP_LOG where DBID = ndbid;
  delete from BACKUP_LOG where DBID = ndbid;
  insert into BACKUP_LOG(DBID,SYS_NAME,DB_NAME,BACKUP_TYPE,START_DATE,STATUS,BACKUP_CLASS)
  values(ndbid,'${SYS_NAME}',dbname,'${BACKUP_TYPE}','${START_DATE}','RUNNING','${NB_ORA_CLASS}');
  commit;
end;
/
EOF

#######################
# compatability check #
#######################
export Db_COMPATABILITY=`sqlplus -s <<EOF
/
set heading off feedback off term off trimspool on pages 0
select substr(value,1,1) from v\\$parameter where name = 'compatible';
EOF`

if [ $Db_COMPATABILITY -eq 8 ];then
  #PIECE_SIZE=""
  echo "\nRunning Version $Db_COMPATABILITY of Database\n"
else
  #PIECE_SIZE="maxpiecesize=2000M"
  echo "\nRunning Version $Db_COMPATABILITY of Database\n"
fi

###############################################
# Validate and Set Channels to use for Backup #
###############################################
#CHANNELS_TO_USE=2=
#CHANNEL_OPTIONS=maxpiecesize=4000M

export CHANNELS_TO_USE=`grep -w CHANNELS_TO_USE $BATCH_PARAMETER |grep -v ^#| awk -F= '{ print $2}'`
export CHANNEL_OPTIONS=`grep -w CHANNEL_OPTIONS $BATCH_PARAMETER |grep -v ^#| awk -F: '{ print $2}'`


SANITY_CHANNELS_TO_USE=1
if [ $CHANNELS_TO_USE -eq 1 ];then
  SANITY_CHANNELS_TO_USE=0
  CH1="allocate channel t1 type ${DEVICE_TYPE} $CHANNEL_OPTIONS ; "
  DYNAMIC_CHANNEL_STRING="${CH1}"
fi

if [ $CHANNELS_TO_USE -eq 2 ];then
  SANITY_CHANNELS_TO_USE=0
  CH1="allocate channel t1 type ${DEVICE_TYPE} $CHANNEL_OPTIONS ; "
  CH2="allocate channel t2 type ${DEVICE_TYPE} $CHANNEL_OPTIONS ; "
  DYNAMIC_CHANNEL_STRING="${CH1}${CH2}"
fi

if [ $CHANNELS_TO_USE -eq 4 ];then
  SANITY_CHANNELS_TO_USE=0
  CH1="allocate channel t1 type ${DEVICE_TYPE} $CHANNEL_OPTIONS ; "
  CH2="allocate channel t2 type ${DEVICE_TYPE} $CHANNEL_OPTIONS ; "
  CH3="allocate channel t3 type ${DEVICE_TYPE} $CHANNEL_OPTIONS ; "
  CH4="allocate channel t4 type ${DEVICE_TYPE} $CHANNEL_OPTIONS ; "
  DYNAMIC_CHANNEL_STRING="${CH1}${CH2}${CH3}${CH4}"
fi

if [ $CHANNELS_TO_USE -eq 8 ];then
  SANITY_CHANNELS_TO_USE=0
  CH1="allocate channel t1 type ${DEVICE_TYPE} $CHANNEL_OPTIONS ; "
  CH2="allocate channel t2 type ${DEVICE_TYPE} $CHANNEL_OPTIONS ; "
  CH3="allocate channel t3 type ${DEVICE_TYPE} $CHANNEL_OPTIONS ; "
  CH4="allocate channel t4 type ${DEVICE_TYPE} $CHANNEL_OPTIONS ; "
  CH5="allocate channel t5 type ${DEVICE_TYPE} $CHANNEL_OPTIONS ; "
  CH6="allocate channel t6 type ${DEVICE_TYPE} $CHANNEL_OPTIONS ; "
  CH7="allocate channel t7 type ${DEVICE_TYPE} $CHANNEL_OPTIONS ; "
  CH8="allocate channel t8 type ${DEVICE_TYPE} $CHANNEL_OPTIONS ; "
  DYNAMIC_CHANNEL_STRING="${CH1}${CH2}${CH3}${CH4}${CH5}${CH6}${CH7}${CH8}"
fi

if [ $SANITY_CHANNELS_TO_USE -eq 1 ];then
  echo "\nInvalid value ${CHANNELS_TO_USE} for CHANNELS_TO_USE specified in parameters.ora"
  echo "Valid values are 1 or 2 or 4 or 8. Aborting...\n"
  exit
fi

echo "\nDynamically set $CHANNELS_TO_USE channels for backup\n"
echo "\nFollowing CHANNELS Dynamically set for RAC Arch backup ...\n"
echo $DYNAMIC_CHANNEL_STRING
echo "\n"


# Start pointer
if [ "$PARA_BACKUP_DEVICE" = "DISK" ]; then
  StartPntrLog=$DB_BACKUP_LOC/Starting_DB_backup_`date +%d`.log
  echo "Start Backup Pointer" > $StartPntrLog
fi


echo "*******************************************"
echo "Database Backup Phase.."
echo "*******************************************"

rman target / << EOF
set echo on
connect catalog $connstrcat
run {
#allocate channel t1 type ${DEVICE_TYPE} maxpiecesize=2000M;
#allocate channel t2 type ${DEVICE_TYPE} maxpiecesize=2000M;
${DYNAMIC_CHANNEL_STRING}
set command id to 'rman';
backup
incremental level = ${backup_level}
tag "$dbtag"
format "$fm"
database ${FILES_PER_SET_VAL};
#release channel t1;
#release channel t2;
}
exit;
EOF

#
#Archive files backup
echo "\n*******************************************************"
echo "Archivelog backup phase.." - Handle RAC And NON RAC Env
echo "*******************************************************"
#

$NB_ORA_SCRIPTS/arch_backup.sh $ORACLE_SID

#
#Contolfiles backup
#
echo "\n*******************************************"
echo "Controlfile backup phase.."
echo "*******************************************"

sqlplus -s <<EOF
/
Prompt
Prompt Backing Control File to trace ...
Prompt
alter database backup controlfile to trace;
EOF

rman target / << EOF
set echo on
connect catalog $connstrcat
run {
#allocate channel t1 type 'sbt_tape';
allocate channel t1 type ${DEVICE_TYPE};
backup
tag "$ctltag"
format "$fmcl"
current controlfile;
release channel t1;
}
EOF

#Resync Catalog database
echo "\n*******************************************"
echo "Resync Catalog Phase.."
echo "*******************************************"

rman target / << EOF
set echo on
connect catalog $connstrcat
run {
allocate channel dev1 type disk;
resync catalog;
release channel dev1;
}
EOF
} >> $logfile

export END_DATE=`date "+%Y/%m/%d %H:%M:%S"`

echo "Start Date: $START_DATE" >>$logfile
echo "End Date : $END_DATE" >>$logfile

#egrep -i "RMAN-|error message stack|RMAN-00569|error occurred" $logfile > /dev/null 2>&1
grep -i "error" $logfile|grep -v "ORA-235"|grep -v "RMAN-06061"|grep -v grep > /dev/null 2>&1
if [ $? = 0 ]; then
  export IMSG="FAILED"
  export REMARKS=`grep -i "ORA-" $logfile|grep -v "ORA-235"|grep -v "RMAN-06061"|grep -v grep`
else
  export IMSG="SUCCESSFUL"
  export REMARKS=""
fi

# End Backup pointer
if [ "$PARA_BACKUP_DEVICE" = "DISK" ]; then
    EndPntrLog=$DB_BACKUP_LOC/End_DB_backup_${IMSG}_`date +%d`.log
    echo "End Backup Pointer" > $EndPntrLog

fi

sqlplus -s /nolog<<EOF>>$logfile
connect /
alter session set nls_date_format='YYYY/MM/DD HH24:MI:SS';
declare
  ndbid  number;
  nsize number;
  bsize number;
begin
  select ltrim(dbid) into ndbid from v\$database;
  select round((sum(bytes)/1024/1024),2) into nsize from dba_data_files;
  select round((sum(blocks*block_size)/1024/1024),2) into bsize
  from   v\$backup_datafile
  where  completion_time >= '${START_DATE}';
  update BACKUP_LOG
  set    END_DATE='${END_DATE}',
         STATUS='$IMSG',
         BACKUP_SIZE=bsize,
         DB_SIZE=nsize,
         REMARKS=substr('${REMARKS}',1,500)
  where  DBID=ndbid
  and    START_DATE='${START_DATE}';
commit;
end;
/
EOF
