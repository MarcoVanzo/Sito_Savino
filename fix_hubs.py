import glob
import re

with open("proposta_tecnica_sdb.html", "r") as f:
    html = f.read()

hubs = [
    "mockup_v3_roster_hub",
    "mockup_v3_ticketing_hub",
    "mockup_v3_b2b_hub",
    "mockup_v3_academy_hub",
    "mockup_v3_sociale_hub",
    "mockup_v3_media_hub"
]

for prefix in hubs:
    # Find the actual file in the directory
    files = glob.glob(prefix + "*.png")
    if files:
        files.sort()
        real_file = files[-1]
        # Replace the missing one with the real one
        html = html.replace(prefix + ".png", real_file)

with open("proposta_tecnica_sdb.html", "w") as f:
    f.write(html)

print("Hub images fixed!")
