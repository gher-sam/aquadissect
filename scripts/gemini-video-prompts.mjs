#!/usr/bin/env node

/**
 * Rabbitfish Guided Dissection Prompt Runner
 *
 * Usage:
 *   node scripts/gemini-video-prompts.mjs --list
 *   node scripts/gemini-video-prompts.mjs --clip 3 --dry-run
 *   node scripts/gemini-video-prompts.mjs --clip 3
 *   node scripts/gemini-video-prompts.mjs --range 1-5
 *   node scripts/gemini-video-prompts.mjs --all
 *
 * Options:
 *   --clip <n>        Run a specific clip number (1-72)
 *   --range <start-end> Run a range of clips (e.g. 1-5)
 *   --all             Run all 72 clips sequentially
 *   --model <name>    Model to use (default: gemini-2.5-flash)
 *   --dry-run         Preview prompt and request payload without calling the API
 *   --list            List all available clip IDs and titles
 */

import { readFileSync, writeFileSync, mkdirSync, existsSync } from 'node:fs';
import { resolve, join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);
const ROOT_DIR = resolve(__dirname, '..');
const PROMPTS_FILE = join(ROOT_DIR, 'docs', 'video-prompts', 'rabbitfish-72-20261030-v1', 'Rabbitfish_72_Sequential_Video_Prompts.json');
const OUTPUT_DIR = join(ROOT_DIR, 'output', 'gemini-api-runs');

// Load environment variables from .env if present
function loadEnv() {
  const envPaths = [
    join(ROOT_DIR, '.env'),
    join(process.env.USERPROFILE || process.env.HOME || '', '.env')
  ];

  for (const envPath of envPaths) {
    if (existsSync(envPath)) {
      try {
        const content = readFileSync(envPath, 'utf8');
        for (const line of content.split('\n')) {
          const trimmed = line.trim();
          if (!trimmed || trimmed.startsWith('#')) continue;
          const eqIdx = trimmed.indexOf('=');
          if (eqIdx > 0) {
            const key = trimmed.slice(0, eqIdx).trim();
            const val = trimmed.slice(eqIdx + 1).trim().replace(/^['"](.*)['"]$/, '$1');
            if (!process.env[key]) {
              process.env[key] = val;
            }
          }
        }
      } catch (err) {
        // ignore read errors
      }
    }
  }
}

loadEnv();

function printHelp() {
  console.log(`
Rabbitfish Video Prompt Runner — AquaDissect
==========================================
Usage:
  node scripts/gemini-video-prompts.mjs [options]

Options:
  --clip <id>         Process a single clip by ID (e.g. --clip 3)
  --range <from-to>   Process a range of clips (e.g. --range 1-5)
  --all               Process all 72 clips sequentially
  --model <name>      API model to query (default: gemini-2.5-flash)
  --dry-run           Inspect prompt payload without sending API call
  --list              List all 72 clip IDs and titles
  --help              Show this help message
`);
}

function getPromptsData() {
  if (!existsSync(PROMPTS_FILE)) {
    console.error(`Error: Prompts file not found at ${PROMPTS_FILE}`);
    process.exit(1);
  }
  return JSON.parse(readFileSync(PROMPTS_FILE, 'utf8'));
}

async function runClip(clip, model, apiKey, isDryRun) {
  const paddedId = String(clip.id).padStart(2, '0');
  console.log(`\n------------------------------------------------------------`);
  console.log(`[Clip ${paddedId}] ${clip.title}`);
  console.log(`Target Duration: ${clip.duration_seconds}s (max ${clip.maximum_duration_seconds}s)`);
  console.log(`Start State: ${clip.start_state}`);
  console.log(`Handoff State: ${clip.handoff_state}`);
  console.log(`Overlay: ${clip.postproduction_overlay}`);
  console.log(`------------------------------------------------------------`);

  const payload = {
    contents: [
      {
        role: 'user',
        parts: [
          {
            text: clip.full_prompt
          }
        ]
      }
    ],
    generationConfig: {
      temperature: 0.2,
      topP: 0.95
    }
  };

  if (isDryRun) {
    console.log(`[DRY RUN] Would send prompt payload to ${model}:`);
    console.log(`Prompt preview (first 250 chars):\n${clip.full_prompt.slice(0, 250)}...\n`);
    return { dryRun: true };
  }

  if (!apiKey) {
    console.error(`\n[ERROR] GEMINI_API_KEY is not set!`);
    console.error(`Please set the GEMINI_API_KEY environment variable or add it to .env`);
    process.exit(1);
  }

  const endpoint = `https://generativelanguage.googleapis.com/v1beta/models/${model}:generateContent?key=${apiKey}`;
  console.log(`Sending request to ${model}...`);
  const startTime = Date.now();

  try {
    const response = await fetch(endpoint, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify(payload)
    });

    const elapsed = ((Date.now() - startTime) / 1000).toFixed(2);

    if (!response.ok) {
      const errText = await response.text();
      console.error(`[FAIL] HTTP ${response.status} (${elapsed}s): ${errText}`);
      return { success: false, status: response.status, error: errText };
    }

    const data = await response.json();
    console.log(`[SUCCESS] Response received in ${elapsed}s`);

    const candidateText = data.candidates?.[0]?.content?.parts?.map(p => p.text).join('\n') || '';
    const usage = data.usageMetadata || {};

    console.log(`Tokens: Prompt: ${usage.promptTokenCount || 0}, Output: ${usage.candidatesTokenCount || 0}, Total: ${usage.totalTokenCount || 0}`);
    console.log(`\nResponse Summary:\n${candidateText.slice(0, 300)}...`);

    // Ensure output directory exists
    if (!existsSync(OUTPUT_DIR)) {
      mkdirSync(OUTPUT_DIR, { recursive: true });
    }

    // Save JSON output
    const jsonPath = join(OUTPUT_DIR, `clip-${paddedId}.json`);
    const record = {
      clipId: clip.id,
      title: clip.title,
      model,
      timestamp: new Date().toISOString(),
      durationSeconds: clip.duration_seconds,
      usage,
      prompt: clip.full_prompt,
      response: candidateText,
      rawResponse: data
    };
    writeFileSync(jsonPath, JSON.stringify(record, null, 2), 'utf8');

    // Save Markdown readable output
    const mdPath = join(OUTPUT_DIR, `clip-${paddedId}.md`);
    const mdContent = `# Clip ${paddedId}: ${clip.title}

- **Model:** ${model}
- **Timestamp:** ${record.timestamp}
- **Prompt Tokens:** ${usage.promptTokenCount || 'N/A'}
- **Output Tokens:** ${usage.candidatesTokenCount || 'N/A'}
- **Handoff:** ${clip.handoff_state}

## Full Prompt
\`\`\`text
${clip.full_prompt}
\`\`\`

## Media service response
${candidateText}
`;
    writeFileSync(mdPath, mdContent, 'utf8');
    console.log(`Saved output to ${jsonPath} and ${mdPath}`);

    return { success: true, record };
  } catch (err) {
    console.error(`[EXCEPTION] ${err.message}`);
    return { success: false, error: err.message };
  }
}

async function main() {
  const args = process.argv.slice(2);
  if (args.length === 0 || args.includes('--help') || args.includes('-h')) {
    printHelp();
    return;
  }

  const data = getPromptsData();
  const clips = data.clips || [];

  if (args.includes('--list')) {
    console.log(`\nRabbitfish Guided Dissection — ${clips.length} Clips Available:`);
    console.log(`================================================================`);
    for (const c of clips) {
      const pid = String(c.id).padStart(2, '0');
      console.log(`  [${pid}] ${c.title} (${c.duration_seconds}s)`);
    }
    console.log(`\nRun a clip with: node scripts/gemini-video-prompts.mjs --clip <id>`);
    return;
  }

  const isDryRun = args.includes('--dry-run');
  const modelIdx = args.indexOf('--model');
  const model = modelIdx !== -1 && args[modelIdx + 1] ? args[modelIdx + 1] : 'gemini-2.5-flash';
  const apiKey = process.env.GEMINI_API_KEY;

  let selectedClips = [];

  const clipIdx = args.indexOf('--clip');
  if (clipIdx !== -1 && args[clipIdx + 1]) {
    const id = parseInt(args[clipIdx + 1], 10);
    const found = clips.find(c => c.id === id);
    if (!found) {
      console.error(`Error: Clip with ID ${id} not found (1-72).`);
      process.exit(1);
    }
    selectedClips.push(found);
  }

  const rangeIdx = args.indexOf('--range');
  if (rangeIdx !== -1 && args[rangeIdx + 1]) {
    const [startStr, endStr] = args[rangeIdx + 1].split('-');
    const start = parseInt(startStr, 10);
    const end = parseInt(endStr, 10);
    if (isNaN(start) || isNaN(end) || start > end) {
      console.error(`Error: Invalid range format "${args[rangeIdx + 1]}". Use format like 1-5.`);
      process.exit(1);
    }
    selectedClips = clips.filter(c => c.id >= start && c.id <= end);
  }

  if (args.includes('--all')) {
    selectedClips = clips;
  }

  if (selectedClips.length === 0) {
    console.error('Error: Please specify --clip <id>, --range <from-to>, or --all.');
    printHelp();
    process.exit(1);
  }

  console.log(`Targeting ${selectedClips.length} clip(s) with model "${model}" (dryRun: ${isDryRun})...`);

  for (const clip of selectedClips) {
    await runClip(clip, model, apiKey, isDryRun);
    // Add small polite delay between multiple clips if not dry-run
    if (!isDryRun && selectedClips.length > 1) {
      await new Promise(r => setTimeout(r, 1000));
    }
  }

  console.log(`\nAll done!`);
}

main().catch(err => {
  console.error('Fatal error:', err);
  process.exit(1);
});
