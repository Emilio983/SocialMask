const DEFAULT_TTL_MS = 2 * 60 * 1000; // 2 minutos

type PasskeyChallenge = {
  challenge: string;
  createdAt: number;
  expiresAt: number;
};

const challengeStore = new Map<string, PasskeyChallenge>();

function pruneExpired(now: number): void {
  for (const [id, entry] of challengeStore.entries()) {
    if (entry.expiresAt <= now) {
      challengeStore.delete(id);
    }
  }
}

export function storePasskeyChallenge(challengeId: string, challenge: string, ttlMs = DEFAULT_TTL_MS): void {
  const now = Date.now();
  pruneExpired(now);
  challengeStore.set(challengeId, {
    challenge,
    createdAt: now,
    expiresAt: now + ttlMs,
  });
}

export function consumePasskeyChallenge(challengeId: string): PasskeyChallenge {
  const now = Date.now();
  pruneExpired(now);

  const entry = challengeStore.get(challengeId);
  if (!entry) {
    throw new Error('Passkey challenge not found or expired');
  }

  challengeStore.delete(challengeId);
  return entry;
}
