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
