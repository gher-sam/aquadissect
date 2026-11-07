import test from 'node:test';
import assert from 'node:assert/strict';
import { webcrypto } from 'node:crypto';
import { createUuid } from '../../resources/js/uuid.js';

const uuidV4 = /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/;

test('native UUID generation keeps the crypto receiver', () => {
  const source = {
    value: '00112233-4455-4677-8899-aabbccddeeff',
    randomUUID() { return this.value; },
    getRandomValues() { assert.fail('Native generation should be used.'); },
  };
  assert.equal(createUuid(source), source.value);
});

test('HTTP fallback preserves random bytes and sets UUID version and variant bits', () => {
  const source = {
    getRandomValues(bytes) {
      assert.ok(bytes instanceof Uint8Array);
      assert.equal(bytes.length, 16);
      bytes.set([0x00, 0x11, 0x22, 0x33, 0x44, 0x55, 0xff, 0x77, 0xff, 0x99, 0xaa, 0xbb, 0xcc, 0xdd, 0xee, 0xff]);
      return bytes;
    },
  };
  assert.equal(createUuid(source), '00112233-4455-4f77-bf99-aabbccddeeff');
});

test('HTTP fallback creates distinct server-compatible submission identifiers', () => {
  const source = { getRandomValues: bytes => webcrypto.getRandomValues(bytes) };
  const ids = Array.from({ length: 100 }, () => createUuid(source));
  ids.forEach(id => assert.match(id, uuidV4));
  assert.equal(new Set(ids).size, ids.length);
});

test('missing browser randomness fails explicitly instead of using weak identifiers', () => {
  assert.throws(() => createUuid({}), /cannot create a submission identifier/);
});
