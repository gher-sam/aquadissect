import test from 'node:test';
import assert from 'node:assert/strict';
import { validPolygon, polygonPath, regionFor, regionMapFor, safeAnatomySource } from '../../resources/js/anatomyRegions.js';
import { builtInRegionMaps } from '../../resources/js/data/anatomyRegionMaps.js';

const square = [[10,10],[30,10],[30,30],[10,30]];
test('outlines reject crossing, zero-area, repeated, out-of-image and executable coordinates', () => {
  assert.equal(validPolygon(square), true);
  for (const p of [[[0,0],[30,30],[0,30],[30,0]],[[0,0],[10,10],[20,20]],[[1,1],[1,1],[10,20]],[[0,0],[101,0],[10,10]],[[0,0],[Infinity,0],[2,2]],[[0,0],['<script>',0],[2,2]]]) assert.equal(validPolygon(p),false);
  assert.equal(polygonPath(square),'M10 10 L30 10 L30 30 L10 30 Z');
  assert.equal(polygonPath([[1,1],[1,1],[1,1]]),'');
});

test('all retained illustration outlines have valid geometry and references', () => {
  for(const map of Object.values(builtInRegionMaps)){
    assert.equal(map.width,1536);assert.equal(map.height,1024);
    assert.ok(map.sources.every(safeAnatomySource));
    for(const part of Object.values(map.parts))assert.ok(part.polygons.every(validPolygon));
  }
});

test('builtin traces require the same image, species, layer and anatomical label', () => {
  const map=Object.values(builtInRegionMaps).find(m=>m.layer==='internal');
  const m={species:{slug:'rabbitfish'},assetRecords:{internal:{sha256:map.imageSha256}},contentData:{anatomy:{}}};
  const part={id:'heart',name:'Heart',layer:'internal'};
  assert.equal(regionFor(m,part).available,true);
  assert.equal(regionFor({...m,species:{slug:'another-species'}},part).available,false);
  assert.equal(regionFor({...m,assetRecords:{internal:{sha256:'changed'}}},part).available,false);
  assert.equal(regionFor(m,{...part,name:'Kidney'}).available,false);
  assert.equal(regionFor(m,{id:'kidney',name:'Kidney',layer:'internal'}).available,false);
  assert.match(regionFor(m,{id:'kidney',name:'Kidney',layer:'internal'}).reason,/distinct kidney boundary/);
});

test('a new species supplies its own regions without a species-specific adapter or hotspot', () => {
  const part={id:'example-fin',name:'Synthetic region',layer:'external'};
  const map={layer:'external',imageSha256:'new-image',width:120,height:80,basis:'illustration',sources:[{title:'Engineering source',url:'https://example.org/reference'}],parts:{'example-fin':{label:part.name,polygons:[square]}},unmapped:{}};
  const m={species:{slug:'qa-future-species'},assetRecords:{external:{sha256:'new-image'}},contentData:{anatomy:{regionMaps:{external:map}}}};
  assert.equal(regionFor(m,part).available,true);
  map.imageSha256='stale';assert.equal(regionMapFor(m,'external'),null);
  map.imageSha256='new-image';map.sources[0].url='javascript:alert(1)';assert.equal(regionFor(m,part).available,false);
  assert.equal(safeAnatomySource({title:'Unsafe',url:'https://user:secret@example.org'}),null);
});

test('explicit unmapped and mismatched package maps never inherit a legacy guessed outline', () => {
  const map=Object.values(builtInRegionMaps).find(m=>m.layer==='internal');
  const m={species:{slug:'rabbitfish'},assetRecords:{internal:{sha256:map.imageSha256}},contentData:{anatomy:{regionMaps:{internal:{...map,parts:{},unmapped:{heart:'New content requires a matching outline.'}}}}}};
  assert.equal(regionFor(m,{id:'heart',name:'Heart',layer:'internal'}).available,false);
  m.contentData.anatomy.regionMaps.internal.imageSha256='wrong';
  assert.equal(regionFor(m,{id:'heart',name:'Heart',layer:'internal'}).available,false);
});
