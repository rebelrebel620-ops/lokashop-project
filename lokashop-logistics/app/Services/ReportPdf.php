<?php
namespace App\Services;

/** Small, dependency-free PDF writer for the fixed report columns. */
class ReportPdf
{
 public static function make(array $headers, $rows, array $filters): string
 {
  $width=770/count($headers);$bottom=44;$y=0;$pages=[];$stream='';$page=0;
  $text=function(float $x,float $y,string $value,int $size=8):string{
   $value=str_replace(['₱',"\r","\n"],['PHP ',' ',' '],$value);
   $value=iconv('UTF-8','Windows-1252//TRANSLIT//IGNORE',$value)?:'';
   $value=preg_replace('/[\x00-\x1F\x7F]/',' ',$value);
   $value=str_replace(['\\','(',')'],['\\\\','\\(','\\)'],$value);
   return sprintf("BT /F1 %d Tf 1 0 0 1 %.2F %.2F Tm (%s) Tj ET\n",$size,$x,$y,$value);
  };
  $wrap=function(string $value)use($width):array{
   $value=str_replace('₱','PHP ',$value);$chars=max(8,(int)(($width-12)/4.3));
   return explode("\n",wordwrap($value,$chars,"\n",true));
  };
  $start=function()use(&$stream,&$y,&$page,$headers,$width,$text,$wrap,$filters,$rows){
   $page++;$stream="0.08 0.25 0.16 rg\n".$text(36,558,'LokaShop | Filtered report',18);
   $stream.=$text(36,538,'From: '.($filters['from']??'All dates').'   To: '.($filters['to']??'All dates').'   Search: '.($filters['q']??'All records'),9);
   $stream.=$text(36,522,'Records: '.count($rows).' | Amounts in PHP | Generated: '.now()->format('Y-m-d H:i'),9);
   $stream.="0.9 0.95 0.92 rg 36 474 770 32 re f 0.08 0.25 0.16 rg\n";
   foreach($headers as $i=>$header)foreach($wrap(ucwords(str_replace('_',' ',$header))) as $j=>$line)$stream.=$text(42+$i*$width,493-$j*10,$line);
   $stream.=$text(36,24,'LokaShop report | Page '.$page,8);$y=462;
  };
  $start();
  foreach($rows as $row){
   $cells=[];$lineCount=1;
   foreach($headers as $header){$value=$row->$header??'';if(in_array($header,['merchandise_total','store_discount','commission','shipping_fee','total'],true)&&is_numeric($value))$value=number_format((float)$value,2,'.',',');$cell=$wrap((string)$value);$cells[]=$cell;$lineCount=max($lineCount,count($cell));}
   $height=$lineCount*11+12;
   if($y-$height<$bottom){$pages[]=$stream;$start();}
   foreach($cells as $i=>$lines)foreach($lines as $j=>$line)$stream.=$text(42+$i*$width,$y-$j*11,$line);
   $y-=$height;$stream.=sprintf("0.85 0.89 0.86 RG 36 %.2F m 806 %.2F l S\n",$y+6,$y+6);
  }
  if(!count($rows))$stream.=$text(42,$y,'No records match the selected filters.',11);
  $pages[]=$stream;
  $objects=[1=>'<< /Type /Catalog /Pages 2 0 R >>',2=>'',3=>'<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>'];$kids=[];
  foreach($pages as $i=>$content){$id=4+$i*2;$sid=$id+1;$kids[]="$id 0 R";$objects[$id]="<< /Type /Page /Parent 2 0 R /MediaBox [0 0 842 595] /Resources << /Font << /F1 3 0 R >> >> /Contents $sid 0 R >>";$objects[$sid]='<< /Length '.strlen($content).">>\nstream\n".$content.'endstream';}
  $objects[2]='<< /Type /Pages /Count '.count($pages).' /Kids ['.implode(' ',$kids).'] >>';ksort($objects);
  $pdf="%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";$offsets=[0];
  foreach($objects as $id=>$object){$offsets[$id]=strlen($pdf);$pdf.="$id 0 obj\n$object\nendobj\n";}
  $xref=strlen($pdf);$pdf.='xref'."\n0 ".(count($objects)+1)."\n0000000000 65535 f \n";
  foreach($objects as $id=>$object)$pdf.=sprintf("%010d 00000 n \n",$offsets[$id]);
  return $pdf.'trailer << /Size '.(count($objects)+1)." /Root 1 0 R >>\nstartxref\n$xref\n%%EOF\n";
 }
}
