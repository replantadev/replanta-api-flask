$files=@(
  'front/hosting-wordpress.content.html',
  'front/eco-hosting.content.html',
  'front/migrar-eco-hosting.content.html',
  'front/replanta-alojamiento-web-ecologico.content.html'
)
$cp1252=[System.Text.Encoding]::GetEncoding(1252)
$utf8=[System.Text.Encoding]::UTF8
$changed=@()
foreach($f in $files){
  if(!(Test-Path $f)){ continue }
  $t=Get-Content -Raw -LiteralPath $f
  $o=$t
  $t=$utf8.GetString($cp1252.GetBytes($t))
  $t=$utf8.GetString($cp1252.GetBytes($t))
  if($f -eq 'front/hosting-wordpress.content.html'){
    $t=$t -replace '<div class="rep-heading-3 price" data-plan="sauce"><span class="original"></span>\$112<span class="rep-text-small">,99</span>&euro;</span><span class="rep-text-small period period--m">/mes</span>\$1129&euro;</span><span class="rep-text-small period period--y">/año</span>','<div class="rep-heading-3 price" data-plan="sauce"><span class="original"></span><span class="amount amount--m">12<span class="rep-text-small">,99</span>&euro;</span><span class="rep-text-small period period--m">/mes</span><span class="amount amount--y">129&euro;</span><span class="rep-text-small period period--y">/año</span>'
    $t=$t -replace '<div class="rep-heading-3 price" data-plan="roble"><span class="original"></span>\$119<span class="rep-text-small">,99</span>&euro;</span><span class="rep-text-small period period--m">/mes</span>\$1199&euro;</span><span class="rep-text-small period period--y">/año</span>','<div class="rep-heading-3 price" data-plan="roble"><span class="original"></span><span class="amount amount--m">19<span class="rep-text-small">,99</span>&euro;</span><span class="rep-text-small period period--m">/mes</span><span class="amount amount--y">199&euro;</span><span class="rep-text-small period period--y">/año</span>'
    $t=$t -replace '<div class="rep-heading-3 price" data-plan="cedro"><span class="original"></span>\$129<span class="rep-text-small">,99</span>&euro;</span><span class="rep-text-small period period--m">/mes</span>\$1299&euro;</span><span class="rep-text-small period period--y">/año</span>','<div class="rep-heading-3 price" data-plan="cedro"><span class="original"></span><span class="amount amount--m">29<span class="rep-text-small">,99</span>&euro;</span><span class="rep-text-small period period--m">/mes</span><span class="amount amount--y">299&euro;</span><span class="rep-text-small period period--y">/año</span>'
  }
  if($t -ne $o){
    Set-Content -LiteralPath $f -Value $t -NoNewline -Encoding UTF8
    $changed += $f
  }
}
"Changed: $($changed.Count)"
$changed
