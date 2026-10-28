// A rejected record stays on this device. It must not block unrelated valid work.
export async function synchronizeRecords(records, { ownerId, send, remove, failed, canContinue = () => true }) {
  const summary = { synced: 0, failed: 0, pending: 0 };
  const own = records.filter(item => item.ownerId && item.ownerId === ownerId);
  for (let i = 0; i < own.length; i += 1) {
    if (!canContinue()) { summary.pending += own.length - i; break; }
    const item = own[i];
    try {
      await send(item);
      await remove(item);
      summary.synced += 1;
    } catch (error) {
      await failed(item, error);
      summary.failed += 1;
      if (!error.status || [401, 419, 429].includes(error.status) || error.status >= 500) {
        summary.pending += own.length - i - 1;
        break;
      }
    }
  }
  return summary;
}
