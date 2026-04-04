[CmdletBinding(PositionalBinding = $false)]
param(
    [Parameter(Mandatory = $true)]
    [string]$EnvPath,

    [string]$ComposeFile,

    [string]$ProjectDirectory,

    [Parameter(ValueFromRemainingArguments = $true)]
    [string[]]$ComposeArguments
)

$ErrorActionPreference = 'Stop'

function Resolve-InputPath {
    param(
        [Parameter(Mandatory = $true)]
        [string]$Path,

        [Parameter(Mandatory = $true)]
        [string]$RepositoryRoot
    )

    if (Test-Path -LiteralPath $Path) {
        return (Resolve-Path -LiteralPath $Path).Path
    }

    $repositoryCandidate = Join-Path $RepositoryRoot $Path

    if (Test-Path -LiteralPath $repositoryCandidate) {
        return (Resolve-Path -LiteralPath $repositoryCandidate).Path
    }

    throw "Path not found: $Path"
}

$scriptRoot = Split-Path -Parent $MyInvocation.MyCommand.Path
$repositoryRoot = (Resolve-Path (Join-Path $scriptRoot '..\..')).Path

$resolvedComposeFile = if ([string]::IsNullOrWhiteSpace($ComposeFile)) {
    (Resolve-Path (Join-Path $scriptRoot 'docker-compose.mysql.minio.yml')).Path
} else {
    Resolve-InputPath -Path $ComposeFile -RepositoryRoot $repositoryRoot
}

$resolvedEnvPath = Resolve-InputPath -Path $EnvPath -RepositoryRoot $repositoryRoot
$envItem = Get-Item -LiteralPath $resolvedEnvPath

$resolvedEnvFile = if ($envItem.PSIsContainer) {
    $candidateEnvFile = Join-Path $resolvedEnvPath '.env'

    if (-not (Test-Path -LiteralPath $candidateEnvFile)) {
        throw "Directory does not contain a .env file: $resolvedEnvPath"
    }

    (Resolve-Path -LiteralPath $candidateEnvFile).Path
} else {
    $resolvedEnvPath
}

$resolvedProjectDirectory = if ([string]::IsNullOrWhiteSpace($ProjectDirectory)) {
    Split-Path -Parent $resolvedComposeFile
} else {
    Resolve-InputPath -Path $ProjectDirectory -RepositoryRoot $repositoryRoot
}

if (-not $ComposeArguments -or $ComposeArguments.Count -eq 0) {
    $ComposeArguments = @('up', '-d', '--build', '--force-recreate', 'ocr')
}

Write-Host "Using compose file: $resolvedComposeFile"
Write-Host "Using env file: $resolvedEnvFile"
Write-Host "Using project directory: $resolvedProjectDirectory"
Write-Host "Running: docker compose $($ComposeArguments -join ' ')"

$dockerArguments = @(
    'compose',
    '--project-directory',
    $resolvedProjectDirectory,
    '--env-file',
    $resolvedEnvFile,
    '-f',
    $resolvedComposeFile
) + $ComposeArguments

& docker @dockerArguments

if ($LASTEXITCODE -ne 0) {
    exit $LASTEXITCODE
}
