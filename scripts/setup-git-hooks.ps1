Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$repoRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$hooksPath = '.githooks'

git -C $repoRoot rev-parse --is-inside-work-tree | Out-Null
git -C $repoRoot config core.hooksPath $hooksPath

Write-Host "Git hooks configured: core.hooksPath=$hooksPath"
Write-Host "Pre-commit hook will bump plugin version and run .github/scripts/check_encoding.py before every commit."
