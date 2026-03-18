$ErrorActionPreference = "Stop"

# Resets test data in the local XAMPP MariaDB database (keeps tables).
#
# Default DB name for this project is `detailsdb` (see Placement-Hub/db-config.php).
# If your MySQL root user has a password, set $DbPass.

$MysqlExe = "C:\\xampp\\mysql\\bin\\mysql.exe"
$DbName = "detailsdb"
$DbUser = "root"
$DbPass = "" # set if needed

$SqlFile = Join-Path $PSScriptRoot "reset-test-data.sql"

if (!(Test-Path $MysqlExe)) { throw "mysql.exe not found at $MysqlExe" }
if (!(Test-Path $SqlFile)) { throw "SQL file not found: $SqlFile" }

$args = @("-u$DbUser")
if ($DbPass -ne "") { $args += "-p$DbPass" }
$args += $DbName

Write-Host "Resetting test data in DB '$DbName'..."
Get-Content -Path $SqlFile | & $MysqlExe @args
Write-Host "Done."

