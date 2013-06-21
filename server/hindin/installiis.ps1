# --------------------------------------------------------------------
# Define the variables.
# --------------------------------------------------------------------
# If you change this to C:\ then you must comment out the section which removes Users permissions from the Dataroot directory!
$DataRoot = "D:\"
$InetPubRoot = "D:\IISData"
$InetPubLog = "E:\Logs"
 
# --------------------------------------------------------------------
# Loading Feature Installation Modules
# --------------------------------------------------------------------
Import-Module ServerManager 

# --------------------------------------------------------------------
# Installing Additional Features
# --------------------------------------------------------------------
Add-WindowsFeature -Name Telnet-Client
 
# --------------------------------------------------------------------
# Installing IIS
# --------------------------------------------------------------------
Add-WindowsFeature -Name Web-Static-Content,Web-Default-Doc,Web-Http-Errors,Web-Http-Redirect,Web-ASP,Web-Asp-Net,Web-Net-Ext,Web-ISAPI-Ext,Web-ISAPI-Filter,Web-Http-Logging,Web-Log-Libraries,Web-Request-Monitor,Web-Url-Auth,Web-Filtering,Web-IP-Security,Web-Stat-Compression,Web-Dyn-Compression,Web-Mgmt-Console,Web-Scripting-Tools,RSAT-Web-Server,WAS

# --------------------------------------------------------------------
# Installing FTP - commented out by default
# --------------------------------------------------------------------
# Add-WindowsFeature -Name Web-Ftp-Service
# $Command = "%windir%\system32\inetsrv\appcmd set config -section:system.applicationHost/sites -siteDefaults.ftpServer.logFile.directory:$InetPubLog\LogFiles"
# cmd.exe /c $Command
# $Command = "%windir%\system32\inetsrv\appcmd set config -section:system.applicationHost/sites -siteDefaults.ftpServer.logFile.localTimeRollover:True"
# cmd.exe /c $Command
# Do not uncomment the command below this line as it doesn't work.  It is meant to enable all log file fields for the FTP server.
# $Command = "%windir%\system32\inetsrv\appcmd set config -section:system.applicationHost/sites -siteDefaults.logFile.logExtFileFlags:Date,Time,ClientIP,UserName,SiteName,ComputerName,ServerIP,Method,UriStem,FtpStatus,Win32Status,BytesSent,BytesRecv,TimeTaken,ServerPort,Host,FtpSubStatus,Session,FullPath,Info,ClientPort"
# cmd.exe /c $Command 
# --------------------------------------------------------------------
# Loading IIS Modules
# --------------------------------------------------------------------
Import-Module WebAdministration
 
# --------------------------------------------------------------------
# Creating IIS Folder Structure
# --------------------------------------------------------------------
New-Item -Path $InetPubRoot -type directory -Force -ErrorAction SilentlyContinue
New-Item -Path $InetPubLog -type directory -Force -ErrorAction SilentlyContinue
 
# --------------------------------------------------------------------
# Setting directory access
# --------------------------------------------------------------------
$Command = "icacls $InetPubRoot /grant BUILTIN\IIS_IUSRS:(OI)(CI)(RX)"
cmd.exe /c $Command
$Command = "icacls $DataRoot /remove:g Users"
cmd.exe /c $Command
$Command = "icacls $InetPubLog /grant ""NT SERVICE\TrustedInstaller"":(OI)(CI)(F)"
cmd.exe /c $Command
 
# --------------------------------------------------------------------
# Setting IIS Variables
# --------------------------------------------------------------------
#Changing Config Defaults
$Command = "%windir%\system32\inetsrv\appcmd set config -section:system.applicationHost/sites -siteDefaults.logFile.directory:$InetPubLog\LogFiles"
cmd.exe /c $Command
$Command = "%windir%\system32\inetsrv\appcmd set config -section:system.applicationHost/log -centralBinaryLogFile.directory:$InetPubLog\LogFiles"
cmd.exe /c $Command
$Command = "%windir%\system32\inetsrv\appcmd set config -section:system.applicationHost/log -centralW3CLogFile.directory:$InetPubLog\LogFiles"
cmd.exe /c $Command
$Command = "%windir%\system32\inetsrv\appcmd set config -section:system.applicationHost/log -centralW3CLogFile.localTimeRollover:True"
cmd.exe /c $Command
$Command = "%windir%\system32\inetsrv\appcmd set config -section:system.applicationHost/log -centralW3CLogFile.logExtFileFlags:BytesRecv,BytesSent,ClientIP,ComputerName,Cookie,Date,Host,HttpStatus,HttpSubStatus,Method,ProtocolVersion,Referer,ServerIP,ServerPort,SiteName,Time,TimeTaken,UriQuery,UriStem,UserAgent,UserName,Win32Status"
cmd.exe /c $Command
$Command = "%windir%\system32\inetsrv\appcmd set config -section:system.applicationHost/sites -siteDefaults.traceFailedRequestsLogging.directory:$InetPubLog\FailedReqLogFiles"
cmd.exe /c $Command
$Command = "%windir%\system32\inetsrv\appcmd set config -section:system.applicationHost/applicationPools -applicationPoolDefaults.recycling.logEventOnRecycle:ConfigChange,IsapiUnhealthy,OnDemand,PrivateMemory,Time,Requests,Schedule,Memory"
cmd.exe /c $Command
$Command = "%windir%\system32\inetsrv\appcmd set config -section:system.applicationHost/sites -siteDefaults.logFile.localTimeRollover:True"
cmd.exe /c $Command
$Command = "%windir%\system32\inetsrv\appcmd set config -section:system.applicationHost/sites -siteDefaults.logFile.logExtFileFlags:BytesRecv,BytesSent,ClientIP,ComputerName,Cookie,Date,Host,HttpStatus,HttpSubStatus,Method,ProtocolVersion,Referer,ServerIP,ServerPort,SiteName,Time,TimeTaken,UriQuery,UriStem,UserAgent,UserName,Win32Status"
cmd.exe /c $Command 
#$Command = "%windir%\system32\inetsrv\appcmd set config -section:system.applicationHost/applicationPools -applicationPoolDefaults.managedRuntimeVersion:v4.0"
#cmd.exe /c $Command 

# --------------------------------------------------------------------
# Resetting IIS
# --------------------------------------------------------------------
$Command = "IISRESET"
Invoke-Expression -Command $Command

# --------------------------------------------------------------------
# Stop Default Web Site
# --------------------------------------------------------------------
# stop-Website -name "Default Web Site"
