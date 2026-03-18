param(
  [Parameter(Mandatory = $false)]
  [string]$Source = "..\\icons\\source-logo.jpg",

  # "contain" = keep full image (no cropping), add padding
  # "cover"   = fill the square (center-crop)
  [Parameter(Mandatory = $false)]
  [ValidateSet("contain","cover")]
  [string]$FitMode = "contain",

  # Background used for "contain" mode.
  # Use "transparent" or a hex color like "#ffffff".
  [Parameter(Mandatory = $false)]
  [string]$Background = "#ffffff"
)

$ErrorActionPreference = "Stop"

Add-Type -AssemblyName System.Drawing

function Resolve-PathFromScriptRoot {
  param([Parameter(Mandatory = $true)][string]$Path)

  $scriptRoot = $PSScriptRoot
  if ([string]::IsNullOrWhiteSpace($scriptRoot)) {
    $scriptRoot = Split-Path -Parent $MyInvocation.MyCommand.Path
  }

  if ([System.IO.Path]::IsPathRooted($Path)) {
    return (Resolve-Path -LiteralPath $Path).Path
  }

  $candidate = Join-Path $scriptRoot $Path
  try {
    return (Resolve-Path -LiteralPath $candidate).Path
  } catch {
    $dir = [System.IO.Path]::GetDirectoryName($candidate)
    $base = [System.IO.Path]::GetFileNameWithoutExtension($candidate)
    foreach ($ext in @(".png", ".jpg", ".jpeg", ".webp")) {
      $alt = Join-Path $dir ($base + $ext)
      if (Test-Path -LiteralPath $alt) {
        return (Resolve-Path -LiteralPath $alt).Path
      }
    }
    throw
  }
}

function New-IcoFromPng {
  param(
    [Parameter(Mandatory = $true)][string]$PngPath,
    [Parameter(Mandatory = $true)][string]$IcoPath
  )

  $pngBytes = [System.IO.File]::ReadAllBytes($PngPath)
  $ms = New-Object System.IO.MemoryStream
  $bw = New-Object System.IO.BinaryWriter($ms)

  # ICONDIR
  $bw.Write([UInt16]0)   # reserved
  $bw.Write([UInt16]1)   # type = icon
  $bw.Write([UInt16]1)   # count

  # ICONDIRENTRY (32x32 PNG)
  $bw.Write([Byte]32)    # width
  $bw.Write([Byte]32)    # height
  $bw.Write([Byte]0)     # colors
  $bw.Write([Byte]0)     # reserved
  $bw.Write([UInt16]1)   # planes
  $bw.Write([UInt16]32)  # bpp
  $bw.Write([UInt32]$pngBytes.Length)
  $bw.Write([UInt32]22)  # offset = 6 + 16

  $bw.Write($pngBytes)
  $bw.Flush()

  [System.IO.File]::WriteAllBytes($IcoPath, $ms.ToArray())

  $bw.Dispose()
  $ms.Dispose()
}

function Save-ResizedSquarePng {
  param(
    [Parameter(Mandatory = $true)][System.Drawing.Image]$Img,
    [Parameter(Mandatory = $true)][int]$Size,
    [Parameter(Mandatory = $true)][string]$OutPath,
    [Parameter(Mandatory = $true)][string]$Mode,
    [Parameter(Mandatory = $true)][string]$Bg
  )

  $bmp = New-Object System.Drawing.Bitmap($Size, $Size)
  $g = [System.Drawing.Graphics]::FromImage($bmp)
  $g.SmoothingMode = [System.Drawing.Drawing2D.SmoothingMode]::HighQuality
  $g.InterpolationMode = [System.Drawing.Drawing2D.InterpolationMode]::HighQualityBicubic
  $g.PixelOffsetMode = [System.Drawing.Drawing2D.PixelOffsetMode]::HighQuality

  if ($Bg -eq "transparent") {
    $g.Clear([System.Drawing.Color]::Transparent)
  } else {
    $bgColor = [System.Drawing.ColorTranslator]::FromHtml($Bg)
    $g.Clear($bgColor)
  }

  $dstRect = New-Object System.Drawing.Rectangle(0, 0, $Size, $Size)

  if ($Mode -eq "cover") {
    # Center-crop to square, then fill destination.
    $srcSize = [Math]::Min($Img.Width, $Img.Height)
    $srcX = [int][Math]::Floor(($Img.Width - $srcSize) / 2)
    $srcY = [int][Math]::Floor(($Img.Height - $srcSize) / 2)
    $srcRect = New-Object System.Drawing.Rectangle($srcX, $srcY, $srcSize, $srcSize)
    $g.DrawImage($Img, $dstRect, $srcRect, [System.Drawing.GraphicsUnit]::Pixel)
  } else {
    # Contain: fit entire image inside square with padding.
    $scale = [Math]::Min($Size / [double]$Img.Width, $Size / [double]$Img.Height)
    $dw = [int][Math]::Round($Img.Width * $scale)
    $dh = [int][Math]::Round($Img.Height * $scale)
    if ($dw -lt 1) { $dw = 1 }
    if ($dh -lt 1) { $dh = 1 }
    $dx = [int][Math]::Floor(($Size - $dw) / 2)
    $dy = [int][Math]::Floor(($Size - $dh) / 2)
    $dest = New-Object System.Drawing.Rectangle($dx, $dy, $dw, $dh)
    $g.DrawImage($Img, $dest)
  }

  $bmp.Save($OutPath, [System.Drawing.Imaging.ImageFormat]::Png)

  $g.Dispose()
  $bmp.Dispose()
}

$sourcePath = Resolve-PathFromScriptRoot -Path $Source
$iconsDir = Resolve-PathFromScriptRoot -Path "..\\icons"

Write-Host "Source: $sourcePath"
Write-Host "OutDir:  $iconsDir"

$img = [System.Drawing.Image]::FromFile($sourcePath)
try {
  Save-ResizedSquarePng -Img $img -Size 512 -OutPath (Join-Path $iconsDir "icon-512.png") -Mode $FitMode -Bg $Background
  Save-ResizedSquarePng -Img $img -Size 192 -OutPath (Join-Path $iconsDir "icon-192.png") -Mode $FitMode -Bg $Background
  Save-ResizedSquarePng -Img $img -Size 180 -OutPath (Join-Path $iconsDir "apple-touch-icon.png") -Mode $FitMode -Bg $Background
  Save-ResizedSquarePng -Img $img -Size 32  -OutPath (Join-Path $iconsDir "favicon-32.png") -Mode "cover" -Bg $Background
  Save-ResizedSquarePng -Img $img -Size 16  -OutPath (Join-Path $iconsDir "favicon-16.png") -Mode "cover" -Bg $Background

  New-IcoFromPng -PngPath (Join-Path $iconsDir "favicon-32.png") -IcoPath (Join-Path $iconsDir "favicon.ico")
} finally {
  $img.Dispose()
}

Write-Host "Done."
