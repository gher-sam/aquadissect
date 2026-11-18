# Anatomical region highlighting and future species

The 2.5D viewer selects traced anatomical regions instead of circular markers.
Outlines use percentages of the exact source image and follow its fitted bounds
when the display changes size. Selecting a part from the list opens its layer.
The same paths support mouse, touch and keyboard selection. Show parts reveals
available outlines; selected structures use a solid coral border and a light fill.
Motion follows the existing device preference.

## Anatomical evidence and limits

Sources inspected on November 18, 2026:

- [FAO Siganidae identification guide](https://www.fao.org/4/y0870e/y0870e27.pdf):
  family and species identification, external fin arrangement and morphology.
  The Siganus guttatus account is on PDF page 11 (printed page 3637).
- [SEAFDEC Aquaculture Extension Manual 70](https://repository.seafdec.org.ph/handle/10862/6396)
  (2022): Siganus guttatus biology and external identification. Its culture
  guidance is not an internal-organ segmentation reference.
- [SEAFDEC Biology and culture of siganids](https://repository.seafdec.org.ph/handle/10862/535)
  (1998): family background and digestive anatomy. Its gastrointestinal coiling
  figure depicts Siganus spinus; it is not a pixel map of Siganus guttatus.
- [Pennsylvania Fish and Boat Commission Internal Anatomy](https://www.pa.gov/content/dam/copapwp-pagov/en/fishandboat/documents/education/activitiesandeducationportal/internal.pdf):
  general bony-fish organ relationships. Species, sex, maturity and specimen
  presentation may alter what can be seen.

The image traces follow visible structures in the retained Rabbitfish artwork.
They do not establish anatomical fidelity of the artwork or a specimen's identity.
The kidney and vent have no sufficiently established boundary in these images and
remain explicitly unmapped. Other unrecognized images or changed labels also
remain unmapped. No dot, ellipse around an old coordinate, generic rectangle or
another species' outline substitutes for missing anatomy. The sources are linked
beside the selected structure. Faculty confirmation remains separate from software
and source-reference checks.

## Package format

The existing ZIP-based `.aqpack` format remains data-only and versioned. A future
species supplies its own identity, natural/external/internal images, anatomy,
lesson, formative quiz, safety notices, references and license records. Its
declared current and earlier lesson targets remain selectable even when an
internal structure is introduced early. Omitted optional learning goals leave
the goal display empty; no goals are invented. Its
`anatomy.json` may include `regionMaps.external` and `regionMaps.internal`:

```json
{
  "layer": "external",
  "imageSha256": "the exact 64-character image SHA-256",
  "width": 1536,
  "height": 1024,
  "basis": "illustration",
  "sources": [{"title": "Verified anatomical reference", "url": "https://official-source.example/reference"}],
  "parts": {
    "structure-id": {
      "label": "The exact structure name from the anatomy file",
      "polygons": [[[10, 10], [20, 10], [20, 20], [10, 20]]]
    }
  },
  "unmapped": {"another-structure-id": "Explain why its visible boundary cannot be established."}
}
```

This is a schema example with placeholder coordinates and a placeholder source;
it is not a biological annotation. Coordinates are finite numbers from 0 to 100,
with the origin at the image's top-left. Each closed polygon has 3–200 distinct
vertices, positive area and no self-intersections. Up to 20 polygons represent
separate visible pieces of one structure, with at most 6,000 vertices per package.
Labels, layers, hashes and dimensions must match. References use HTTPS without
credentials. Arbitrary SVG, HTML, scripts and executable viewer adapters are not
accepted as annotation data. Legacy hotspot metadata may remain in older packages
but is not rendered as dots by the 2.5D viewer.

## Preparing and reviewing content

Authorized authors can open Create revised fish lesson, then Trace anatomical
structure outlines. Select a part, trace its boundary on the image, finish the
outline, enter references and save a new draft version. Coordinate entry supplies
the same function without pointer input. Unfinished outlines block saving.
Reset restores the retained outline; Mark this part as not outlined records a
reason. Uploading a complete anatomy JSON file takes precedence over the editor.

Replacing an image clears its previous region map instead of reusing stale
coordinates. Save the image draft first and reopen it to trace the new image.
Keep a version out of catalog inclusion while no outlines are available. Each
new version requires independent faculty review and administrator publication;
the uploader cannot approve their own content. Existing immutable files, earlier
approvals, assignments, scores and learner records remain retained.

## Adding another species

The default visible study focus stays Rabbitfish. A complete new package may set
`catalogVisibility` to `listed`; no species-specific JavaScript adapter or code
whitelist edit is needed. Every structure must then have an image-matched outline
or a documented unmapped reason, and the package must contain at least one outline.
Import through the existing instructor package upload. It remains a draft until
independent review and administrator publication. Students receive only their
explicitly assigned published version and must satisfy the existing prerequisite.
Older hidden species and historical records are not automatically re-enabled.

Catalog inclusion can also be selected while preparing a new revision. An
unlisted draft revision of a previously listed species remains available to its
authorized staff for continued work. Reviewers should inspect source identity,
image fidelity, organ boundaries, references, lesson targets and safety separately.

Status: Implemented but awaiting validation. Engineering verification, image
alignment, controlled future-species import and release evidence are retained
separately from faculty acceptance and research findings.
