import { escapeHtml as e } from './format.js';

export const reportNumber = (value, suffix = '') => value === null || value === undefined ? 'Not recorded' : `${new Intl.NumberFormat('en-PH', { maximumFractionDigits: 2 }).format(value)}${suffix}`;
export const reportDate = value => value ? new Intl.DateTimeFormat('en-PH', { dateStyle: 'medium', timeStyle: 'short', timeZone: 'Asia/Manila' }).format(new Date(value)) : 'Not recorded';
export const reportColors = ['#0f766e', '#155eef', '#b7791f', '#64748b', '#7c3aed'];

export function reportChartDefinitions(r) {
  const completion = r.charts.completion;
  const trend = r.charts.trend.points;
  const definitions = [
    { key: 'completion', title: 'Assigned activity completion', type: 'bar', horizontal: true,
      note: 'Current assigned learners. Select an activity to inspect its records.', labels: completion.map(c => c.label), drillKeys: completion.map(c => c.key),
      series: [
        { name: 'Completed', values: completion.map(c => c.completed) },
        { name: 'In progress', values: completion.map(c => c.inProgress) },
        { name: 'Prerequisite pending', values: completion.map(c => c.blocked) },
        { name: 'Not recorded', values: completion.map(c => c.notRecorded) },
        { name: 'Exception', values: completion.map(c => c.exception) },
      ] },
    { key: 'trend', title: 'Recorded activity over time', type: 'line', note: `Saved records by ${r.charts.trend.interval}. Learners may create more than one record.`,
      labels: trend.map(p => p.date), series: [{ name: 'Records', values: trend.map(p => p.records) }, { name: 'Unique learners', values: trend.map(p => p.learners) }] },
    { key: 'distribution', title: 'Latest formative scores', type: 'bar', note: `${r.summary.scoreSamples} latest results · one per learner, section and lesson version in this period.`,
      labels: r.summary.scoreSamples ? r.charts.distribution.map(p => p.label) : [], series: [{ name: 'Latest results', values: r.charts.distribution.map(p => p.count) }] },
  ];
  const f = r.forecast;
  if (f.status === 'available') {
    const dates = [...new Set([...f.history.map(p => p.date), ...f.projection.map(p => p.date)])];
    const actual = new Map(f.history.map(p => [p.date, p.completed]));
    const projected = new Map(f.projection.map(p => [p.date, p]));
    definitions.push({ key: 'forecast', title: 'Class completion forecast', type: 'line', note: f.assumption, labels: dates,
      series: [
        { name: 'Recorded', values: dates.map(d => actual.get(d) ?? null) },
        { name: 'Expected at recent pace', values: dates.map(d => projected.get(d)?.expected ?? null), dashed: true, color: '#b57210' },
        { name: 'Slower recent pace', values: dates.map(d => projected.get(d)?.low ?? null), dashed: true, color: '#c69c57', band: true },
        { name: 'Faster recent pace', values: dates.map(d => projected.get(d)?.high ?? null), dashed: true, color: '#c69c57', fill: '-1' },
      ] });
  }
  r.assessments.forEach((a, index) => {
    if (!a.matchedPairs) return;
    definitions.push({ key: `assessment-${index}`, title: a.title, type: 'bar', note: `${a.instrumentVersion} · ${a.matchedPairs} matched pairs. Recorded differences do not establish effectiveness.`,
      labels: ['Pretest', 'Posttest'], series: [{ name: 'Mean percent', values: [a.preMean, a.postMean] }] });
  });
  for (const definition of definitions) {
    const palette = definition.key === 'completion' ? reportColors : definition.key === 'forecast' ? ['#155eef', '#b7791f', '#b68632', '#b68632'] : ['#155eef', '#0f766e'];
    definition.series.forEach((series, index) => { series.color = palette[index % palette.length]; });
  }
  return definitions;
}

export function chartCard(definition) {
  const table = `<details class="analytics-chart-data"><summary>View chart data</summary><div class="analytics-table-scroll"><table><caption class="sr-only">${e(definition.title)}</caption><thead><tr><th scope="col">${definition.key === 'completion' ? 'Activity' : 'Category'}</th>${definition.series.map(s => `<th scope="col">${e(s.name)}</th>`).join('')}</tr></thead><tbody>${definition.labels.map((label, index) => `<tr><th scope="row">${definition.drillKeys ? `<button type="button" data-report-drill="${e(definition.drillKeys[index])}">${e(label)}</button>` : e(label)}</th>${definition.series.map(s => `<td>${reportNumber(s.values[index])}</td>`).join('')}</tr>`).join('')}</tbody></table></div></details>`;
  return `<section class="analytics-card analytics-chart-card"><div class="analytics-card-title"><h2>${e(definition.title)}</h2><span class="analytics-chart-label">${definition.type === 'line' ? 'Trend' : 'Comparison'}</span></div><p class="analytics-card-description">${e(definition.note)}</p>${definition.labels.length ? `<div class="analytics-chart-scroll"><div class="analytics-chart-wrap" ${definition.horizontal ? `style="height:${Math.max(250, definition.labels.length * 52)}px"` : ''}><canvas id="report-chart-${definition.key}" role="img" aria-label="${e(definition.title)}. The same values are available in the chart data table."></canvas></div></div><div class="analytics-chart-legend" role="group" aria-label="Visible chart series">${definition.series.map((s, i) => `<button type="button" data-chart-toggle="${definition.key}:${i}" aria-pressed="true"><span style="background:${s.color || reportColors[i % reportColors.length]}" aria-hidden="true"></span>${e(s.name)}</button>`).join('')}</div>${table}` : '<div class="analytics-empty"><i data-lucide="chart-no-axes-combined" aria-hidden="true"></i><strong>No recorded data</strong><p>Values will appear when records match these filters.</p></div>'}</section>`;
}

export function overviewView(r, definitions) {
  const s = r.summary;
  const cards = [
    ['Active learners', reportNumber(s.learners), `${s.enrollments} section enrollments`, 'users-round'],
    ['Activity completion', reportNumber(s.completionRate, '%'), `${s.completedItems} completed · ${s.exceptionItems} exceptions`, 'badge-check'],
    ['Mean latest formative score', reportNumber(s.meanScore, '%'), `${s.scoreSamples} latest results in this period`, 'chart-no-axes-combined'],
    [r.meta.role === 'student' ? 'Your next steps' : 'Learners needing review', reportNumber(s.needsReview), 'Explainable current-work signals', 'clipboard-list'],
  ];
  if (s.practiceXp !== null) cards.push(['Your practice XP', reportNumber(s.practiceXp), 'Private practice rewards · separate from grades', 'sparkles']);
  const forecast = r.forecast;
  const forecastCard = forecast.status === 'available' ? chartCard(definitions.find(c => c.key === 'forecast')) : `<section class="analytics-card"><div class="analytics-card-title"><h2>Class completion forecast</h2><span class="analytics-chart-label">${forecast.status === 'complete' ? 'Complete' : 'Estimate'}</span></div><div class="analytics-empty"><i data-lucide="chart-no-axes-combined" aria-hidden="true"></i><strong>${forecast.status === 'complete' ? 'Activity complete' : forecast.status === 'insufficient' ? 'More history is needed' : 'Choose a forecast scope'}</strong><p>${e(forecast.message)}</p></div><p class="analytics-card-description">Forecasts use recorded completion pace. They do not predict grades or practical competence.</p></section>`;
  return `<div class="analytics-kpis">${cards.map(([label, value, detail, icon]) => `<article class="analytics-kpi"><div><span>${e(label)}</span><i data-lucide="${icon}" aria-hidden="true"></i></div><strong>${e(value)}</strong><small>${e(detail)}</small></article>`).join('')}</div><p class="analytics-status-basis">${e(r.meta.statusBasis)}</p><div class="analytics-insights"><i data-lucide="info" aria-hidden="true"></i><p><strong>${s.recordCount} saved records in this period.</strong> ${s.historicalCount ? `${s.historicalCount} unattributed historical records are listed separately in Records.` : 'Section records retain their actual activity and version.'} ${s.meanScore === null ? 'A zero score is shown only when it was actually recorded.' : `Median latest formative score: ${reportNumber(s.medianScore, '%')}.`}</p></div><div class="analytics-grid">${definitions.filter(c => ['completion', 'trend', 'distribution'].includes(c.key)).map(chartCard).join('')}${forecastCard}</div>${forecast.status === 'available' ? `<p class="analytics-forecast-basis">Basis: ${forecast.observedCompletions} first completions over ${forecast.windowDays} complete days · ${forecast.cohort} assigned learners · ${forecast.horizon}-day horizon. Estimated completions by horizon: <strong>approximately ${forecast.expectedAtHorizon}</strong>${forecast.expectedCompletionDate ? ` · Estimated completion date: ${e(forecast.expectedCompletionDate)}` : ' · Whole-cohort completion is beyond this horizon.'}</p>` : ''}`;
}

export function supportView(r) {
  return `<section class="analytics-card"><div class="analytics-card-title"><h2>${r.meta.role === 'student' ? 'Your next steps' : 'Learner support review'}</h2><span class="analytics-chart-label">Current work</span></div><p class="analytics-card-description">These reasons identify work to review. They do not assign grades or establish that a learner is inactive. Score-based review is ${r.filters.scoreThreshold === undefined ? 'disabled' : `set below ${reportNumber(r.filters.scoreThreshold, '%')}`}.</p>${r.support.length ? `<div class="analytics-table-scroll"><table><thead><tr><th>Learner</th><th>Section and activity</th><th>Review reasons</th><th>Recorded result</th><th>Details</th></tr></thead><tbody>${r.support.map(s => `<tr><td><strong>${e(s.learner)}</strong><small>${e(s.studentId)}</small></td><td>${e(s.section)}<small>${e(s.activity)}</small></td><td>${s.reasons.map(reason => `<span class="analytics-reason">${e(reason)}</span>`).join('')}</td><td>${reportNumber(s.latestScore, '%')}<small>${e(s.status)}</small></td><td><button type="button" class="portal-secondary" data-review-learner="${s.learnerId}" data-review-class="${s.classId}" data-review-activity="${e(s.activityKey)}">View records</button></td></tr>`).join('')}</tbody></table></div>` : '<div class="analytics-empty"><i data-lucide="shield-check" aria-hidden="true"></i><strong>No review signals in this selection</strong><p>Current rules found no matching work to flag.</p></div>'}</section>`;
}

export function assessmentsView(r, definitions) {
  return `<section class="analytics-card"><div class="analytics-card-title"><h2>Paired assessment comparisons</h2><span class="analytics-chart-label">By instrument</span></div><p class="analytics-card-description">Each study and instrument version stays separate. Means and differences use matched, non-voided pairs. A partner submitted before the selected period is retained when the other phase falls within it.</p>${r.assessments.length ? `<div class="analytics-table-scroll"><table><thead><tr><th>Instrument</th><th>Matched pairs</th><th>Pretest mean</th><th>Posttest mean</th><th>Difference</th><th>Unpaired / voided</th></tr></thead><tbody>${r.assessments.map(a => `<tr><th scope="row"><button type="button" data-report-drill="${e(a.key)}">${e(a.title)}</button><small>${e(a.instrumentVersion)}</small></th><td>${a.matchedPairs}</td><td>${reportNumber(a.preMean, '%')}</td><td>${reportNumber(a.postMean, '%')}</td><td>${reportNumber(a.difference, ' pp')}</td><td>${a.unpairedLearners} / ${a.voidedAttempts}</td></tr>`).join('')}</tbody></table></div>` : '<div class="analytics-empty"><strong>No assessment records match</strong><p>Recorded formal assessments appear here separately from formative practice.</p></div>'}</section><div class="analytics-grid">${definitions.filter(c => c.key.startsWith('assessment-')).map(chartCard).join('')}</div>`;
}

export function recordsView(data) {
  return `${data.rows.length ? `<div class="analytics-table-scroll"><table><thead><tr><th>Learner</th><th>Section</th><th>Activity</th><th>Result</th><th>Status</th><th>Recorded PHT</th></tr></thead><tbody>${data.rows.map(r => `<tr><td><strong>${e(r.learner)}</strong><small>${e(r.studentId)}</small></td><td>${e(r.section)}${r.attributed ? '' : '<span class="analytics-reason">Historical · unattributed</span>'}</td><td>${e(r.activity)}<small>${e(r.kind)}</small></td><td>${r.xp !== undefined ? `${reportNumber(r.xp)} XP` : r.score !== null && r.total > 0 ? `${r.score} / ${r.total}` : 'Not recorded'}</td><td>${e(r.status)}</td><td>${e(reportDate(r.date))}</td></tr>`).join('')}</tbody></table></div>` : '<div class="analytics-empty"><strong>No matching records</strong><p>Change the report filters or clear the table search.</p></div>'}<div class="analytics-pagination"><span>${data.total} matching records · Page ${data.page} of ${data.lastPage}</span><div><button type="button" class="portal-secondary" data-record-page="${data.page - 1}" ${data.page <= 1 ? 'disabled' : ''}>Previous</button><button type="button" class="portal-secondary" data-record-page="${data.page + 1}" ${data.page >= data.lastPage ? 'disabled' : ''}>Next</button></div></div>`;
}
