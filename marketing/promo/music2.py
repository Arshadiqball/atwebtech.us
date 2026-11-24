"""Synthesizes the soundtrack for the second promo: 96 BPM, laid-back keys and a syncopated beat (music2.wav).

A bar is 2.5 s, so 24 bars make the minute and every scene cut in build2.py falls on a bar. No samples.
"""
import os, wave
import numpy as np

SR = 44100
LEN = 60.5
N = int(SR * LEN)
rng = np.random.default_rng(11)
bus = {k: np.zeros((N, 2)) for k in ('drums', 'music', 'fx')}
STEP = 60 / 96 / 4  # a sixteenth
BAR = STEP * 16


def add(b, at, x, l=1.0, r=1.0):
    i = int(at * SR)
    if i >= N or i < 0:
        return
    x = x[: N - i]
    bus[b][i:i + len(x), 0] += x * l
    bus[b][i:i + len(x), 1] += x * r


def tt(d):
    return np.arange(int(SR * d)) / SR


def hz(n):
    return 440 * 2 ** ((n - 69) / 12)


def fade(x, a=0.004, r=0.03):
    na, nr = max(1, int(a * SR)), max(1, int(r * SR))
    x = x.copy()
    x[:na] *= np.linspace(0, 1, na)
    x[-nr:] *= np.linspace(1, 0, nr)
    return x


def lowpass(x, w):
    return np.convolve(x, np.ones(w) / w, 'same')


def keys(n, d=0.55, vel=1.0):
    """Electric-piano-like: a soft fundamental, a quick bell partial, slow tremolo."""
    t = tt(d)
    f = hz(n)
    x = np.sin(2 * np.pi * f * t) * np.exp(-t * 3.2)
    x += 0.35 * np.sin(2 * np.pi * 2 * f * t) * np.exp(-t * 6)
    x += 0.22 * vel * np.sin(2 * np.pi * 4.01 * f * t) * np.exp(-t * 22)
    return fade(x * (1 + 0.12 * np.sin(2 * np.pi * 5 * t)))


def bell(n, d=0.7):
    t = tt(d)
    f = hz(n)
    return fade((np.sin(2 * np.pi * f * t) + 0.5 * np.sin(2 * np.pi * 3.01 * f * t) * np.exp(-t * 9) + 0.25 * np.sin(2 * np.pi * 5.4 * f * t) * np.exp(-t * 16)) * np.exp(-t * 5))


def pad(n, d):
    t = tt(d)
    x = 0
    for det in (-0.005, 0.005):
        f = hz(n) * (1 + det)
        x = x + sum(np.sin(2 * np.pi * f * k * t + k) / k ** 1.8 for k in range(1, 5))
    return fade(x / 2, 0.4, 0.6)


def bass(n, d=0.3):
    t = tt(d)
    f = hz(n)
    return fade((np.sin(2 * np.pi * f * t) + 0.45 * np.sin(4 * np.pi * f * t) * np.exp(-t * 8) + 0.15 * np.sin(6 * np.pi * f * t) * np.exp(-t * 12)) * np.exp(-t * 4.5), 0.004, 0.04)


def kick():
    t = tt(0.4)
    f = 44 + 100 * np.exp(-t * 26)
    return fade(np.sin(2 * np.pi * np.cumsum(f) / SR) * np.exp(-t * 7), 0.001, 0.04)


def snare():
    t = tt(0.24)
    n = np.diff(rng.standard_normal(len(t) + 1))
    return fade(lowpass(n, 2) * np.exp(-t * 22) * 0.8 + np.sin(2 * np.pi * 190 * t) * np.exp(-t * 30) * 0.6, 0.001, 0.02)


def snap():
    t = tt(0.09)
    return np.diff(rng.standard_normal(len(t) + 1)) * np.exp(-t * 70) * (1 + np.sin(2 * np.pi * 1800 * t))


def hat(d=0.045, dec=95):
    t = tt(d)
    return np.diff(np.diff(rng.standard_normal(len(t) + 2))) * np.exp(-t * dec) * 0.3


def whoosh(d=0.55, up=True):
    t = tt(d)
    n = lowpass(rng.standard_normal(len(t)), 20)
    return fade(n * ((t / d) ** 2.2 if up else (1 - t / d) ** 2.2), 0.01, 0.01)


def riser(d):
    t = tt(d)
    n = np.diff(rng.standard_normal(len(t) + 1)) * 0.5 + lowpass(rng.standard_normal(len(t)), 10)
    f = 220 * 2 ** (t / d * 2.2)
    return fade((n * 0.6 + np.sin(2 * np.pi * np.cumsum(f) / SR) * 0.25) * (t / d) ** 2, 0.01, 0.005)


def thud(g=1.0, d=0.9):
    t = tt(d)
    f = 40 + 70 * np.exp(-t * 16)
    return fade(np.sin(2 * np.pi * np.cumsum(f) / SR) * np.exp(-t * 4.5) + lowpass(rng.standard_normal(len(t)), 5) * np.exp(-t * 9) * 0.5, 0.001, 0.1) * g


def blip(n, d=0.16):
    t = tt(d)
    f = hz(n) * (1 + 0.5 * np.exp(-t * 60))
    return fade(np.sin(2 * np.pi * np.cumsum(f) / SR) * np.exp(-t * 18), 0.002, 0.02)


def tick():
    t = tt(0.02)
    return np.diff(rng.standard_normal(len(t) + 1)) * np.exp(-t * 300)


# Fmaj7, G6, Em7, Am7: one bar each.
CH = [(41, [53, 57, 60, 64]), (43, [55, 59, 62, 64]), (40, [52, 55, 59, 62]), (45, [57, 60, 64, 67])]
STAB = {0: 1.0, 3: 0.7, 6: 0.85, 10: 0.75, 13: 0.5}
LEAD = {0: 76, 3: 79, 6: 81, 8: 79, 11: 76, 14: 72}
LEAD2 = {0: 74, 3: 76, 6: 79, 8: 76, 11: 74, 14: 72}


def groove(t):
    return (10 <= t < 47.5) or (52.5 <= t < 57.5)


for bar in range(24):
    t0 = bar * BAR
    root, notes = CH[bar % 4]
    brk = 47.5 <= t0 < 52.5
    if brk or t0 >= 57.5:
        if t0 < 57.5:
            for i, n in enumerate(notes):
                add('music', t0, pad(n, BAR + 0.3) * 0.09, 1 - 0.3 * (i % 2), 0.7 + 0.3 * (i % 2))
        continue
    add('music', t0, pad(notes[0] - 12, BAR + 0.2) * (0.05 if groove(t0) else 0.03))
    for s in range(16):
        t = t0 + s * STEP + (0.018 if s % 2 else 0)  # a little swing
        if s in STAB:
            for i, n in enumerate(notes):
                add('music', t, keys(n, 0.6, STAB[s]) * 0.085 * STAB[s], 1 - 0.35 * (i % 2), 0.65 + 0.35 * (i % 2))
        if groove(t):
            if s in (0, 7, 10):
                add('drums', t, kick() * 0.9)
                add('music', t, bass(root) * 0.36)
            if s == 14:
                add('music', t, bass(root + 12, 0.2) * 0.26)
            if s in (4, 12):
                add('drums', t, snare() * 0.5, 0.9, 1)
            if s % 2 == 0:
                add('drums', t, hat() * (0.5 if s % 4 == 2 else 0.3), 0.75, 1)
            elif t >= 20:
                add('drums', t, hat(0.03, 140) * 0.18, 1, 0.7)
            if s == 14:
                add('drums', t, hat(0.16, 22) * 0.3, 1, 0.8)
            if (30 <= t < 47.5 or t >= 52.5) and s in LEAD:
                n = (LEAD if bar % 2 == 0 else LEAD2)[s]
                x = bell(n) * 0.1
                add('music', t, x, 0.55, 1)
                add('music', t + 3 * STEP, x * 0.35, 1, 0.45)
        else:
            if s in (4, 12):
                add('drums', t, snap() * 0.22, 1, 0.8)
            if t >= 5 and s % 2 == 0:
                add('drums', t, hat() * 0.3, 0.75, 1)
            if t >= 5 and s == 0:
                add('drums', t, kick() * 0.7)
                add('music', t, bass(root) * 0.3)

add('fx', 8.75, riser(1.25) * 0.16)
add('fx', 51.25, riser(1.25) * 0.18)
for at, g in ((10, 0.8), (52.5, 0.9), (57.5, 0.8)):
    add('fx', at, thud(g, 1.4))
for cut in (5, 10, 20, 30, 40, 47.5, 52.5):
    add('fx', cut - 0.42, whoosh(0.45) * 0.28, 0.6, 1)
    add('fx', cut + 0.05, whoosh(0.5, False) * 0.2, 1, 0.6)
for at in (0.15, 0.37, 0.59, 0.95, 1.25):          # the hook's words
    add('fx', at, thud(0.45, 0.4))
for at in (47.6, 48.85, 50.1):                      # Trend. Draft. Done.
    add('fx', at, thud(1.0, 1.1))
    add('fx', at, snare() * 0.4)
for i, at in enumerate((7.1, 7.4, 7.7, 8.0)):       # the four sources
    add('fx', at, blip(72 + [0, 4, 7, 12][i]) * 0.22, 0.7, 1)
for at in (11.2, 11.5, 11.8, 30.7, 31.3, 31.9):     # cards landing
    add('fx', at + 0.25, thud(0.3, 0.3))
for at in (16.25, 45.0):                            # the two clicks, then a two-note chime
    add('fx', at, tick() * 0.5)
    add('fx', at + 0.02, blip(76, 0.3) * 0.26)
    add('fx', at + 0.14, blip(83, 0.5) * 0.26)
for i in range(38):                                 # typing
    add('fx', 22.9 + i * 0.09 + rng.uniform(-0.012, 0.012), tick() * 0.1, 0.8, 1)
for n in (41, 53, 60, 64, 69, 72):                  # last chord
    add('music', 57.5, keys(n, 2.6) * 0.16)
    add('music', 57.5, bell(n + 12, 2.0) * 0.04)

t = np.arange(N) / SR
duck = np.ones(N)
m = np.array([groove(x) for x in t[::441]]).repeat(441)[:N]
duck[m] = 1 - 0.3 * np.exp(-((t[m] - 10) % BAR) * 7)
out = bus['drums'] + bus['music'] * duck[:, None] + bus['fx']
k = int(1.2 * SR)
out[int(59.3 * SR):int(59.3 * SR) + k] *= np.linspace(1, 0, k)[:, None]
out[int(59.3 * SR) + k:] = 0
out = np.tanh(out * 1.7)
out = out / np.abs(out).max() * 0.89
with wave.open(os.path.join(os.path.dirname(os.path.abspath(__file__)), 'music2.wav'), 'w') as w:
    w.setnchannels(2); w.setsampwidth(2); w.setframerate(SR)
    w.writeframes((out * 32767).astype('<i2').tobytes())
print('music2 ok')
