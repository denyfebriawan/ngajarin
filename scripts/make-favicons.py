"""Regenerate public/apple-touch-icon.png and public/favicon.ico:  python scripts/make-favicons.py public

Draw Ngajarin's "N" logo as PNG files with only the standard library (no image packages here).

Same geometry as public/favicon.svg, on a 24x24 grid: a teal rounded square and a white stroke
through the points (7,17.5) (7,6.5) (17,17.5) (17,6.5). Each pixel is sampled 4x4 times for
smooth (anti-aliased) edges.
"""
import math
import struct
import sys
import zlib

TEAL = (15, 118, 110)  # #0f766e, teal-700
WHITE = (255, 255, 255)
POINTS = [(7, 17.5), (7, 6.5), (17, 17.5), (17, 6.5)]
STROKE = 2.75
RADIUS = 5.5


def segment_distance(px, py, ax, ay, bx, by):
    """Distance from point P to the line segment AB."""
    dx, dy = bx - ax, by - ay
    t = max(0.0, min(1.0, ((px - ax) * dx + (py - ay) * dy) / (dx * dx + dy * dy)))
    return math.hypot(px - (ax + t * dx), py - (ay + t * dy))


def inside_rounded_square(x, y, radius):
    """Whether (x, y) is inside the 24x24 square with rounded corners."""
    cx = min(max(x, radius), 24 - radius)
    cy = min(max(y, radius), 24 - radius)
    return math.hypot(x - cx, y - cy) <= radius


def render(size, rounded):
    samples = 4
    pixels = bytearray()
    for row in range(size):
        pixels.append(0)  # PNG filter byte: none
        for col in range(size):
            background = stroke = 0
            for sy in range(samples):
                for sx in range(samples):
                    x = (col + (sx + 0.5) / samples) * 24 / size
                    y = (row + (sy + 0.5) / samples) * 24 / size
                    if not rounded or inside_rounded_square(x, y, RADIUS):
                        background += 1
                        if min(segment_distance(x, y, *POINTS[i], *POINTS[i + 1]) for i in range(3)) <= STROKE / 2:
                            stroke += 1
            total = samples * samples
            s = stroke / total
            colour = [round(TEAL[c] * (1 - s) + WHITE[c] * s) for c in range(3)] if background else [0, 0, 0]
            pixels.extend(colour + [round(255 * background / total)])
    return png(size, bytes(pixels))


def png(size, raw):
    def chunk(kind, data):
        return struct.pack('>I', len(data)) + kind + data + struct.pack('>I', zlib.crc32(kind + data))

    header = struct.pack('>IIBBBBB', size, size, 8, 6, 0, 0, 0)  # 8-bit RGBA
    return b'\x89PNG\r\n\x1a\n' + chunk(b'IHDR', header) + chunk(b'IDAT', zlib.compress(raw, 9)) + chunk(b'IEND', b'')


def ico(images):
    """An .ico file holding PNG images (supported by every browser since Windows Vista)."""
    out = struct.pack('<HHH', 0, 1, len(images))
    offset = 6 + 16 * len(images)
    for size, data in images:
        out += struct.pack('<BBBBHHII', size % 256, size % 256, 0, 0, 1, 32, len(data), offset)
        offset += len(data)
    return out + b''.join(data for _, data in images)


public = sys.argv[1]
# iPhones round the corners themselves, so the touch icon is a full square.
open(f'{public}/apple-touch-icon.png', 'wb').write(render(180, rounded=False))
open(f'{public}/favicon.ico', 'wb').write(ico([(size, render(size, rounded=True)) for size in (16, 32, 48)]))
print('written')
