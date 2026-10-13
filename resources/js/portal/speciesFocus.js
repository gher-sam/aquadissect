// Presentation scope only: retained packages, downloads and results are not deleted.
export function focusedSpecies(records = [], slugs = ['rabbitfish']) {
  return slugs.length ? records.filter(record => slugs.includes(record.slug)) : records;
}
export function focusedClasses(classes = [], species = [], slugs = ['rabbitfish']) {
  if (!slugs.length) return classes;
  const packages = new Map(species.map(record => [String(record.id), record.slug]));
  return classes.map(course => ({ ...course, assignments: (course.assignments || []).filter(assignment =>
    slugs.includes(assignment.speciesSlug || packages.get(String(assignment.packageId ?? assignment.speciesPackageId)))) }));
}

// Staff lesson browsing has one entry per species; management retains every version.
export function staffLessonVersions(records = []) {
  const grouped = new Map();
  for (const record of records) {
    if (record.status === 'archived') continue;
    const previous = grouped.get(record.slug);
    const current = item => item.status === 'published' && item.isCurrent;
    const newer = previous && String(record.version).localeCompare(String(previous.version), undefined, { numeric: true }) > 0;
    if (!previous || (current(record) && !current(previous)) || (current(record) === current(previous) && newer)) {
      grouped.set(record.slug, record);
    }
  }
  return [...grouped.values()];
}
