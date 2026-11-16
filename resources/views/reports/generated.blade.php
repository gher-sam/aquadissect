<!doctype html>
<html lang="en"><head><meta charset="utf-8"><title>AquaDissect Learning Analytics Report</title>
<style>
@page { margin: 155px 34px 100px; }
body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #172d4b; }
header { position: fixed; top: -140px; left: 0; right: 0; height: 125px; border-bottom: 1px solid #173d74; }
header .university { width: 240px; height: auto; } header .bagong { float: right; width: 56px; }
header p { margin: 2px 0; font-size: 8px; line-height: 1.2; } header .values { font-size: 6px; margin-top: 4px; }
footer { position: fixed; bottom: -79px; left: 0; right: 0; height: 70px; border-top: 1px solid #d8e0ec; padding-top: 5px; }
footer .qs { width: 115px; } footer .socotec { width: 88px; float: right; } footer p { font-size: 7px; margin: 4px 0; }
h1 { color: #000; font-size: 19px; margin: 0 0 10px; } h2 { color: #000; font-size: 13px; margin: 16px 0 8px; page-break-after: avoid; }
p { line-height: 1.5; margin: 5px 0; } .muted { color: #526078; }
table { border-collapse: collapse; width: 100%; table-layout: fixed; font-size: 10px; margin: 8px 0 15px; }
.detailed table { font-size: 9px; }
.summary-table td, .summary-table th { padding: 4px 6px; }
th { background: #eaf0fa; color: #173d74; text-align: left; font-weight: bold; }
th, td { padding: 6px; border: 1px solid #d8e0ec; vertical-align: top; overflow-wrap: break-word; word-wrap: break-word; }
thead { display: table-header-group; } tr { page-break-inside: avoid; } .chart-page { page-break-before: always; }
.chart { width: 100%; height: auto; display: block; margin: 0 auto; } .detailed .chart { width: 74%; } .source-tables { page-break-before: always; } .table-section { margin-top: 14px; page-break-inside: avoid; } .note { font-size: 9px; color: #526078; }
</style></head><body class="{{ $layout }}">
<header><img class="university" src="{{ \App\Services\Reports\ReportBrand::dataUri('university') }}" alt="Southern Leyte State University"><img class="bagong" src="{{ \App\Services\Reports\ReportBrand::dataUri('bagong-pilipinas') }}" alt="Bagong Pilipinas">
<p>{{ \App\Services\Reports\ReportBrand::CAMPUS }}</p><p>{{ \App\Services\Reports\ReportBrand::EMAIL }} | {{ \App\Services\Reports\ReportBrand::WEBSITE }}</p><p class="values">{{ \App\Services\Reports\ReportBrand::VALUES }}</p></header>
<footer><img class="qs" src="{{ \App\Services\Reports\ReportBrand::dataUri('qs-stars') }}" alt="Institutional QS branding"><img class="socotec" src="{{ \App\Services\Reports\ReportBrand::dataUri('socotec') }}" alt="Institutional SOCOTEC branding"><p>AquaDissect | Bontoc Campus | Generated {{ \Illuminate\Support\Carbon::parse($report['meta']['generatedAt'])->timezone('Asia/Manila')->format('Y-m-d H:i') }} PHT | {{ ucfirst(str_replace('_', ' ', $report['meta']['role'])) }}</p></footer>
<h1>Learning Analytics Report</h1>
<p>{{ \App\Services\Reports\ReportBrand::period($report) }}</p><p>{{ \App\Services\Reports\ReportBrand::scope($report) }}</p>
<p class="muted">{{ $report['meta']['statusBasis'] }}</p>
@php($summary = $tables[0])
<h2>Summary</h2><table class="summary-table"><colgroup><col style="width:40%"><col style="width:10%"><col style="width:40%"><col style="width:10%"></colgroup><thead><tr><th>Metric</th><th>Value</th><th>Metric</th><th>Value</th></tr></thead><tbody>@foreach(array_chunk($summary['rows'], 2) as $pair)<tr>@foreach($pair as $row)<td>{{ $row[0] }}</td><td>{{ $row[1] === null ? 'Not recorded' : $row[1] }}</td>@endforeach @if(count($pair) === 1)<td></td><td></td>@endif</tr>@endforeach</tbody></table>
<h2>Forecast interpretation</h2><p>{{ $report['forecast']['message'] }}</p>@if(isset($report['forecast']['assumption']))<p>{{ $report['forecast']['assumption'] }}</p>@endif
<p class="note">{{ $report['meta']['boundary'] }} Score review threshold: {{ $report['filters']['scoreThreshold'] ?? 'Disabled' }}.</p>
@foreach($charts as $chart)<section class="chart-page"><h2>{{ $chart['title'] }}</h2><img class="chart" src="data:image/png;base64,{{ base64_encode(file_get_contents($chart['image'])) }}" alt="{{ $chart['title'] }}"><p class="note">{{ $chart['note'] }}</p></section>@endforeach
<div class="source-tables">@foreach(array_slice($tables, 1) as $table)@if(count($table['rows']))<section class="table-section"><h2>{{ $table['name'] }}</h2><table><thead><tr>@foreach($table['headers'] as $header)<th>{{ $header }}</th>@endforeach</tr></thead><tbody>@foreach($table['rows'] as $row)<tr>@foreach($row as $value)<td>{{ $value === null ? 'Not recorded' : $value }}</td>@endforeach</tr>@endforeach</tbody></table></section>@endif @endforeach</div>
<p class="note">Missing values are shown as Not recorded. Zero is a recorded numeric value. Institutional artwork follows the retained Bontoc reference; this report has its own AquaDissect identity.</p>
</body></html>
