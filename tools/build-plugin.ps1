<#
.SYNOPSIS
	Builds the installable plugin zip at the repository root.

.DESCRIPTION
	The zip is what actually gets uploaded to a site, so it must never disagree with the source
	it was built from, and it must never carry another site's content. Three things enforce that
	here:

	  * The version is READ FROM THE PLUGIN HEADER, never passed in. A build cannot be labelled
	    with a version the code does not claim.
	  * The header and the NVM_VR_VERSION constant are compared, and the build ABORTS if they
	    differ. Both have to be bumped by hand, and forgetting one ships a plugin whose asset
	    URLs still carry the old ?ver=. The browser then serves yesterday's CSS from cache and
	    the change looks like it never deployed. Failing here is far cheaper than debugging that.
	  * The bundled product page export is inspected, and the build ABORTS when it carries the
	    site it was authored on rather than the site it will be installed on: an absolute asset
	    URL, a baked date, a literal brand where the shortcode belongs, a literal short
	    description where the dynamic tag belongs, or a buy box with no price widget.

	The file list comes from git rather than from the filesystem, so whatever an editor, an agent
	or a tool leaves lying around inside the plugin folder cannot end up inside a release.
	Uncommitted work IS included: the zip is for testing what you have right now, not only what
	you have pushed.

	Only one zip survives a build. "The latest version" is not a claim worth making next to three
	older ones.

.EXAMPLE
	powershell -ExecutionPolicy Bypass -File tools\build-plugin.ps1
#>

#requires -Version 5.1
[CmdletBinding()]
param(
	# Both paths resolve in the body, not here. Under [CmdletBinding()] the script binds its
	# parameters as an advanced function, and $PSScriptRoot is still empty at that point - a
	# default of (Join-Path $PSScriptRoot ...) fails with "the argument is null or empty" on
	# Windows PowerShell 5.1. It is populated normally once the body runs.

	# Plugin folder to package. Its leaf name becomes the slug, and therefore the folder
	# WordPress installs into, so it has to stay the real plugin directory.
	[string] $Source = '',

	# Where the zip lands. The repository root by default: the point is to have it at hand.
	[string] $OutputDir = ''
)

$ErrorActionPreference = 'Stop'

$scriptRoot = if ($PSScriptRoot) { $PSScriptRoot } else { Split-Path -Parent $MyInvocation.MyCommand.Path }

if (-not $Source)    { $Source    = Join-Path $scriptRoot '..\plugin\nvm-variation-rows' }
if (-not $OutputDir) { $OutputDir = Join-Path $scriptRoot '..' }

$sourcePath = (Resolve-Path -LiteralPath $Source).Path
$outputPath = (Resolve-Path -LiteralPath $OutputDir).Path
$slug       = Split-Path -Path $sourcePath -Leaf
$mainFile   = Join-Path $sourcePath "$slug.php"

if (-not (Test-Path -LiteralPath $mainFile)) {
	throw "No main plugin file at $mainFile. A WordPress plugin folder must contain <slug>.php."
}

$body = Get-Content -LiteralPath $mainFile -Raw -Encoding UTF8

$headerMatch = [regex]::Match($body, '(?m)^\s*\*\s*Version:\s*(\S+)\s*$')
if (-not $headerMatch.Success) {
	throw "No 'Version:' line in the plugin header of $mainFile."
}
$version = $headerMatch.Groups[1].Value

$constMatch = [regex]::Match($body, "NVM_VR_VERSION'\s*,\s*'([^']+)'")
if (-not $constMatch.Success) {
	throw "No NVM_VR_VERSION define in $mainFile."
}
if ($constMatch.Groups[1].Value -ne $version) {
	throw "Version mismatch: header says $version, NVM_VR_VERSION says $($constMatch.Groups[1].Value). Bump both."
}

# The product page is not transcribed into PHP the way the card is: it ships as the export of the
# script that authored it, against a live site. That is what stops a hundred controls from
# drifting into a second copy - and it is also how the authoring site leaks. An image widget
# carries the absolute URL of the server it was built on, a text widget carries whatever copy was
# on screen that afternoon, and both then land on every product of every site the installer runs
# on. 1.5.0 shipped a dev-site logo, a literal brand name, a literal short description and a
# delivery date already in the past. These checks are the price of shipping an export.

$templateRel  = 'assets/templates/nvm-ficha-producto.json'
$templatePath = Join-Path $sourcePath $templateRel
$authoredPath = Join-Path $scriptRoot '..\elementor-template\nvm-ficha-producto-template.json'

if (-not (Test-Path -LiteralPath $templatePath)) {
	throw "No product page export at $templatePath. NVM_VR_Product_Builder reads it at install time."
}

$template = Get-Content -LiteralPath $templatePath -Raw -Encoding UTF8

try {
	$null = ConvertFrom-Json $template
}
catch {
	throw "$templateRel is not valid JSON: $($_.Exception.Message)"
}

# Any absolute URL in the export is the authoring site's. The installing site has no such
# attachment, so the widget hotlinks across domains and shows somebody else's brand.
$foreign = [regex]::Match($template, 'https?:\\?/\\?/[^"]{1,120}')
if ($foreign.Success) {
	throw "$templateRel carries an absolute URL from the site it was authored on: $($foreign.Value)"
}

# A date is true on the day it is exported and wrong every day after, on a template installed
# months later against somebody else's carrier.
$baked = [regex]::Match($template, '\d{1,2}\\?/\d{1,2}\\?/\d{4}')
if ($baked.Success) {
	throw "$templateRel carries a hardcoded date: $($baked.Value)"
}

# What has to be bound rather than typed, and the widget without which a product the variation
# rows do not support renders no price anywhere on the page.
$required = [ordered]@{
	'[nvm_brand]'               = 'the brand line must be the shortcode, not a literal brand name'
	'post-excerpt'              = 'the short description must be the dynamic tag, not literal copy'
	'woocommerce-product-price' = 'the buy box must carry a price widget: the rows only price the products the renderer supports'
}

foreach ($needle in $required.Keys) {
	if (-not $template.Contains($needle)) {
		throw "$templateRel is missing $needle - $($required[$needle])"
	}
}

# The bundled copy IS the authored export. A divergence means one of the two was edited alone,
# which is the drift that shipping the export was meant to make impossible.
if (Test-Path -LiteralPath $authoredPath) {
	if ((Get-Content -LiteralPath $authoredPath -Raw -Encoding UTF8) -cne $template) {
		throw "elementor-template/nvm-ficha-producto-template.json and $templateRel disagree. They are one export in two places; re-copy, do not edit one of them."
	}
}

# --cached lists what is committed, --others what is new, --exclude-standard drops anything
# gitignored. Together: exactly the files that belong to the plugin, and nothing else.
Push-Location $sourcePath
try {
	$files = @(git ls-files --cached --others --exclude-standard) | Where-Object { $_ }
	if ($LASTEXITCODE -ne 0) {
		throw 'git ls-files failed. This script packages from the repository, so it must run inside it.'
	}
}
finally {
	Pop-Location
}

if (-not $files) {
	throw "git lists no files under $sourcePath. Nothing to package."
}

# Entries are named by hand, and NEITHER Compress-Archive NOR ZipFile::CreateFromDirectory is
# used to build them. On Windows both write the entry names with backslashes, which the zip
# format does not allow - the separator must be a forward slash. Windows unpacks such an archive
# anyway, so it looks correct locally, but PHP on the server reads each name literally: WordPress
# ends up with eighteen loose files called "nvm-variation-rows\includes\class-....php" and the
# plugin never appears in the list. git already reports its paths with forward slashes, so
# prefixing the slug is all the naming that is needed - and it means no staging copy either.
# Two assemblies: FileSystem carries ZipFile and the CreateEntryFromFile extension, while
# ZipArchiveMode and CompressionLevel live in the core Compression one.
Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem

# Every previous build goes, including this version's: a rebuild has to replace its predecessor
# rather than sit beside a stale copy under the same name.
Get-ChildItem -LiteralPath $outputPath -Filter "$slug-*.zip" -File -ErrorAction SilentlyContinue |
	Remove-Item -Force

$zipPath = Join-Path $outputPath "$slug-$version.zip"
# 'Create' and 'Optimal' are passed as strings so the enums resolve when the call runs rather
# than when the file is parsed - a type literal is looked up before Add-Type has executed.
$archive = [System.IO.Compression.ZipFile]::Open($zipPath, 'Create')

try {
	foreach ($relative in $files) {
		[System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile(
			$archive,
			(Join-Path $sourcePath $relative),
			"$slug/$relative",
			'Optimal'
		) | Out-Null
	}
}
finally {
	$archive.Dispose()
}

# Cheap insurance against the above regressing: a malformed archive is invisible until someone
# installs it on a live site.
$check = [System.IO.Compression.ZipFile]::OpenRead($zipPath)
try {
	$bad    = @($check.Entries | Where-Object { $_.FullName.Contains('\') })
	$loose  = @($check.Entries | Where-Object { -not $_.FullName.StartsWith("$slug/") })
	$count  = $check.Entries.Count
}
finally {
	$check.Dispose()
}

if ($bad)   { throw "$zipPath stores $($bad.Count) entries with backslash separators." }
if ($loose) { throw "$zipPath stores entries outside the $slug/ root: $($loose[0].FullName)" }

$size = [math]::Round((Get-Item -LiteralPath $zipPath).Length / 1KB, 1)
Write-Host "Built $slug-$version.zip ($count files, $size KB)"
