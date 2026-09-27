<?php
namespace App\Http\Controllers;

use App\Services\SourceRelease;
use Illuminate\Http\Request;

class SourceHandoverController extends Controller
{
    private const HEADERS = ['Cache-Control'=>'private, no-store, max-age=0','X-Content-Type-Options'=>'nosniff'];
    public function index(SourceRelease $release) { return response()->json($release->manifest())->withHeaders(self::HEADERS); }
    public function preview(Request $request, SourceRelease $release)
    {
        $data=$request->validate(['path'=>'required|string|max:240']); $m=$release->manifest();
        $content=$release->read($m,$data['path']);
        abort_if(strlen($content)>262144 || !mb_check_encoding($content,'UTF-8') || str_contains($content,"\0"),422,'Preview is available for UTF-8 text files up to 256 KB. Download this file instead.');
        return response()->json(['path'=>$data['path'],'content'=>$content])->withHeaders(self::HEADERS);
    }
    public function download(Request $request, SourceRelease $release)
    {
        $m=$release->manifest(); $path=$release->archive($m);
        $release->audit($request->user()->id,$m['revision'],'archive');
        return response()->download($path,'AquaDissect-source-'.substr($m['revision'],0,12).'.zip',self::HEADERS);
    }
    public function file(Request $request, SourceRelease $release)
    {
        $data=$request->validate(['path'=>'required|string|max:240']); $m=$release->manifest();
        $content=$release->read($m,$data['path']); $release->audit($request->user()->id,$m['revision'],$data['path']);
        return response($content)->withHeaders(self::HEADERS+['Content-Type'=>'application/octet-stream','Content-Disposition'=>'attachment; filename="'.basename($data['path']).'"']);
    }
}
