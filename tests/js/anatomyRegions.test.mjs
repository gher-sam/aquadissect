import test from 'node:test';
import assert from 'node:assert/strict';
import { validPolygon, polygonPath, regionFor, regionMapFor, safeAnatomySource, sourceAppliesTo } from '../../resources/js/anatomyRegions.js';
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

test('references bind to the exact structure ID and label while legacy image references remain supported', () => {
  const part={id:'0',name:'Synthetic region',layer:'external'},source={title:'Engineering reference',url:'https://example.org/reference',appliesTo:[{id:'0',label:'Synthetic region'}]};
  assert.equal(sourceAppliesTo(source,part),true);
  assert.equal(sourceAppliesTo(source,{...part,id:'another'}),false);
  assert.equal(sourceAppliesTo(source,{...part,name:'Different region'}),false);
  assert.equal(sourceAppliesTo({...source,appliesTo:{0:'Synthetic region'}},part),false);
  assert.equal(sourceAppliesTo({title:source.title,url:source.url},part),true);
  const map={layer:'external',imageSha256:'fixture',width:120,height:80,basis:'photograph',sources:[source],parts:{0:{label:part.name,polygons:[square]}},unmapped:{}};
  const manifest={assetRecords:{external:{sha256:'fixture'}},contentData:{anatomy:{regionMaps:{external:map}}}};
  assert.equal(regionFor(manifest,part).available,true);
  map.sources={malformed:true};assert.equal(regionFor(manifest,part).available,false);
});

test('the curated kidney review uses only its same-species study and does not inherit another organ reference', () => {
  const map=Object.values(builtInRegionMaps).find(m=>m.layer==='internal'),kidney={id:'kidney',name:'Kidney',layer:'internal'},heart={id:'heart',name:'Heart',layer:'internal'};
  const manifest={species:{slug:'rabbitfish'},assetRecords:{internal:{sha256:map.imageSha256}},contentData:{anatomy:{structures:[kidney,heart]}}};
  const region=regionFor(manifest,kidney);
  assert.equal(region.available,false);assert.equal(region.sources.length,1);assert.equal(region.sources[0].url,'https://doi.org/10.1155/tbed/2656593');assert.match(region.sources[0].scope,/diseased kidney tissue/);
  assert.ok(regionFor(manifest,heart).sources.every(source=>source.id!=='guttatus-kidney-2026'));
  const filtered=regionMapFor(manifest,'internal');assert.ok(filtered.sources.every(source=>source.appliesTo.every(target=>['kidney','heart'].includes(target.id))));
  assert.equal(regionFor(manifest,{...kidney,name:'Different organ'}).sources.length,0);
});
