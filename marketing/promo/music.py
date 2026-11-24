"""Synthesizes the 60 s soundtrack for the promo (120 BPM, cuts on the bar). Every sound is generated here; no samples."""
import os, wave
import numpy as np

SR = 44100
LEN = 60.5
N = int(SR * LEN)
rng = np.random.default_rng(7)
mixes = {k: np.zeros((N, 2)) for k in ('drums', 'music', 'fx')}


def add(bus, at, x, l=1.0, r=1.0):
    i = int(at * SR)
    if i >= N:
        return
    x = x[: N - i]
    mixes[bus][i:i + len(x), 0] += x * l
    mixes[bus][i:i + len(x), 1] += x * r


def tt(d):
    return np.arange(int(SR * d)) / SR


def hz(n):  # midi note
    return 440 * 2 ** ((n - 69) / 12)


def fade(x, a=0.005, r=0.02):
    na, nr = int(a * SR), int(r * SR)
    x = x.copy()
    x[:na] *= np.linspace(0, 1, na)
    x[-nr:] *= np.linspace(1, 0, nr)
    return x


def pluck(n, d=0.5, bright=1.0):
    t = tt(d)
    f = hz(n)
    x = sum(np.sin(2 * np.pi * f * k * t) / k * np.exp(-t * (3 + 2.2 * k / bright)) for k in range(1, 9))
    return fade(x * np.exp(-t * 3))


def pad(n, d):
    t = tt(d)
    x = 0
    for det in (-0.004, 0, 0.004):
        f = hz(n) * (1 + det)
        x = x + sum(np.sin(2 * np.pi * f * k * t + k) / k ** 1.6 for k in range(1, 6))
    return fade(x / 3, 0.35, 0.5)


def bass(n, d=0.24):
    t = tt(d)
    f = hz(n)
    return fade((np.sin(2 * np.pi * f * t) + 0.35 * np.sin(4 * np.pi * f * t) + 0.12 * np.sin(6 * np.pi * f * t)) * np.exp(-t * 5), 0.004, 0.03)


def kick():
    t = tt(0.36)
    f = 46 + 120 * np.exp(-t * 30)
    return fade(np.sin(2 * np.pi * np.cumsum(f) / SR) * np.exp(-t * 8) + 0.25 * np.exp(-t * 300) * rng.standard_normal(len(t)) * 0.3, 0.001, 0.03)


def hat(d=0.05, dec=90):
    t = tt(d)
    return np.diff(rng.standard_normal(len(t) + 1)) * np.exp(-t * dec) * 0.5


def clap():
    t = tt(0.22)
    n = np.diff(rng.standard_normal(len(t) + 1))
    n = np.convolve(n, np.ones(3) / 3, 'same')
    env = sum(np.exp(-np.maximum(t - o, 0) * 38) * (t >= o) for o in (0, 0.011, 0.023)) / 3
    return n * env


def lowpass(x, w):
    return np.convolve(x, np.ones(w) / w, 'same')


def whoosh(d=0.6, up=True):
    t = tt(d)
    n = lowpass(rng.standard_normal(len(t)), 24)
    e = (t / d) ** 2.2 if up else (1 - t / d) ** 2.2
    return fade(n * e, 0.01, 0.01)


def riser(d=2.0):
    t = tt(d)
    n = np.diff(rng.standard_normal(len(t) + 1)) * 0.5 + lowpass(rng.standard_normal(len(t)), 12)
    f = 200 * 2 ** (t / d * 2.4)
    tone = np.sin(2 * np.pi * np.cumsum(f) / SR)
    return fade((n * 0.6 + tone * 0.25) * (t / d) ** 2, 0.01, 0.005)


def impact():
    t = tt(1.6)
    f = 38 + 60 * np.exp(-t * 14)
    return fade(np.sin(2 * np.pi * np.cumsum(f) / SR) * np.exp(-t * 3) + lowpass(rng.standard_normal(len(t)), 6) * np.exp(-t * 5) * 0.5, 0.001, 0.2)


def blip(n, d=0.16):
    t = tt(d)
    f = hz(n) * (1 + 0.5 * np.exp(-t * 60))
    return fade(np.sin(2 * np.pi * np.cumsum(f) / SR) * np.exp(-t * 18), 0.002, 0.02)


# A minor, F, C, G: two seconds each.
CH = [(45, [57, 60, 64, 69]), (41, [57, 60, 65, 69]), (48, [55, 60, 64, 67]), (43, [55, 59, 62, 67])]
ARP = [0, 2, 1, 3, 2, 1, 3, 2]
B = 0.5
full = lambda t: (12 <= t < 49) or (54 <= t < 58)

for bar in range(30):
    t0 = bar * 2.0
    root, notes = CH[bar % 4]
    quiet = t0 < 8
    # pad
    g = 0.045 if quiet else (0.13 if 49 <= t0 < 54 else 0.06)
    for i, n in enumerate(notes[:3]):
        add('music', t0, pad(n - (12 if quiet else 0), 2.25) * g, 1 - 0.25 * (i % 2), 0.75 + 0.25 * (i % 2))
    if t0 >= 58:
        continue
    for s in range(16):  # sixteenths
        t = t0 + s * B / 4
        if full(t):
            if s % 4 == 0:
                add('drums', t, kick() * 0.85)
            if s % 8 == 4:
                add('drums', t, clap() * 0.42, 0.9, 1)
            if s % 4 == 2:
                add('drums', t, hat(0.09, 45) * 0.16, 0.8, 1)
            elif s % 2 == 1 and t >= 28:
                add('drums', t, hat() * 0.07, 1, 0.7)
            if s % 2 == 0:
                add('music', t, bass(root - (0 if s % 8 else 0)) * (0.34 if s % 4 else 0.22))
        elif t >= 8 and s % 8 == 4 and t < 54:
            add('drums', t, clap() * 0.3)
        elif t < 8 and s % 4 == 0:
            add('drums', t, hat(0.03, 160) * 0.1, 0.6 + 0.4 * (s % 8 == 0), 1)
            if s == 0 or (t >= 4 and s == 8):
                add('drums', t, kick() * 0.55)
        # arpeggio: eighths in the intro of the reveal, sixteenths after
        if t >= 8 and (s % 2 == 0 or (t >= 12 and not 49 <= t < 54)):
            n = notes[ARP[s % 8]] + (12 if s % 8 in (3, 6) else 0) + (12 if 28 <= t < 34 or t >= 54 else 0)
            v = (0.19 if 49 <= t < 54 else 0.1) * (1.0 if s % 4 == 0 else 0.7)
            x = pluck(n, 0.45, 1.3 if t >= 28 else 1.0) * v
            add('music', t, x, 1, 0.55)
            add('music', t + 0.375, x * 0.38, 0.4, 1)
            add('music', t + 0.75, x * 0.16, 1, 0.4)

# snare roll into the first drop, risers and impacts
for i in range(16):
    add('drums', 11 + i / 16, clap() * (0.12 + 0.02 * i))
for at in (6, 10, 47, 52):
    add('fx', at, riser(2.0) * 0.16)
for at, g in ((8, 0.9), (12, 0.75), (28, 0.6), (49, 0.7), (54, 0.9), (58, 0.8)):
    add('fx', at, impact() * g)
for cut in (4, 12, 20, 28, 34, 42, 49, 54):
    add('fx', cut - 0.45, whoosh(0.5) * 0.3, 0.6, 1)
    add('fx', cut, whoosh(0.5, False) * 0.2, 1, 0.6)
# hook word hits
for at in (0.15, 0.43, 0.85, 1.17, 4.1, 5.0, 6.0):
    add('fx', at, impact()[: int(0.5 * SR)] * 0.4 * np.linspace(1, 0, int(0.5 * SR)))
for at in (1.9, 2.2, 2.5, 2.8):
    add('fx', at, blip(76) * 0.2)
# interface sounds that follow the picture
for i in range(7):
    add('fx', 29.5 + i * 0.5, blip(72 + [0, 4, 7, 12, 7, 12, 16][i]) * 0.2, 0.6, 1)
for at in (26.5, 39.5):
    add('fx', at, blip(76, 0.3) * 0.25)
    add('fx', at + 0.11, blip(83, 0.45) * 0.25)
# final chord
for n in (45, 57, 64, 69, 72, 76):
    add('music', 58, pluck(n, 2.3, 2.0) * 0.16)

t = np.arange(N) / SR
duck = np.ones(N)
m = np.array([full(x) for x in t[::441]]).repeat(441)[:N]
duck[m] = 1 - 0.55 * np.exp(-(t[m] % B) * 9)
out = mixes['drums'] + mixes['music'] * duck[:, None] + mixes['fx']
out[: int(0.02 * SR)] *= np.linspace(0, 1, int(0.02 * SR))[:, None]
k = int(1.2 * SR)
out[int(59.3 * SR):int(59.3 * SR) + k] *= np.linspace(1, 0, k)[:, None]
out[int(59.3 * SR) + k:] = 0
out = np.tanh(out * 1.6)
out = out / np.abs(out).max() * 0.89
with wave.open(os.path.join(os.path.dirname(os.path.abspath(__file__)), 'music.wav'), 'w') as w:
    w.setnchannels(2); w.setsampwidth(2); w.setframerate(SR)
    w.writeframes((out * 32767).astype('<i2').tobytes())
print('music ok', round(LEN, 1))
