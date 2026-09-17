import fs from 'node:fs';
import path from 'node:path';

export type E2eFixture = {
  wallet_a: {
    email: string;
    person_name: string;
    lead_title: string;
    open_conversation_id: number;
    closed_conversation_id: number;
  };
  wallet_b: {
    email: string;
    person_name: string;
    lead_title: string;
    conversation_id: number;
  };
  operational_conversation_id: number;
  blind_conversation_id: number;
  claim_user_email: string;
  pipeline_ids: number[];
  stage_ids: number[];
};

export function fixturePath(): string {
  return process.env.E2E_FIXTURE_FILE ?? path.resolve('.playwright-e2e-fixtures.json');
}

export function e2eFixture(): E2eFixture {
  const file = fixturePath();
  if (!fs.existsSync(file)) {
    throw new Error(`E2E fixture file is missing: ${file}`);
  }

  const value = JSON.parse(fs.readFileSync(file, 'utf8')) as Partial<E2eFixture>;
  if (
    !value.wallet_a?.email
    || !value.wallet_a?.open_conversation_id
    || !value.wallet_a?.closed_conversation_id
    || !value.wallet_b?.email
    || !value.wallet_b?.conversation_id
    || !value.operational_conversation_id
    || !value.blind_conversation_id
    || !value.claim_user_email
    || !Array.isArray(value.pipeline_ids)
    || value.pipeline_ids.length < 2
    || !Array.isArray(value.stage_ids)
    || value.stage_ids.length < 2
  ) {
    throw new Error(`E2E fixture file is incomplete: ${file}`);
  }

  return value as E2eFixture;
}

export function requiredEnv(name: string): string {
  const value = process.env[name];
  if (!value) {
    throw new Error(`${name} is required for TopwebChat E2E acceptance`);
  }

  return value;
}
