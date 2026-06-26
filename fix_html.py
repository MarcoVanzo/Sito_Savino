import re
import os
import glob

# Load original mockups block
with open("mockups_block.html", "r") as f:
    mockups_html = f.read()

# We need to replace the image filenames with the v2 ones.
# The v2 images start with "mockup_v2_".
# Let's find all src="mockup_...png" in mockups_html
images_in_html = re.findall(r'src="(mockup_[^"]+\.png)"', mockups_html)

for img in images_in_html:
    # Example: mockup_home_1781616069814.png -> we want mockup_v2_home_*.png
    # Extract the core name:
    m = re.match(r'mockup_([a-z0-9_]+)_\d+\.png', img)
    if m:
        core_name = m.group(1)
        # Find the matching v2 image
        search_pattern = f"mockup_v2_{core_name}_*.png"
        v2_files = glob.glob(search_pattern)
        if v2_files:
            v2_files.sort()
            new_img = v2_files[-1]
            mockups_html = mockups_html.replace(img, new_img)

# Now read the current html
with open("proposta_tecnica_sdb.html", "r") as f:
    current_html = f.read()

# Insert the mockups_html right before <!-- 12 — DETTAGLIO HUB GRAFICI v2.0 -->
if "<!-- 12 — DETTAGLIO HUB GRAFICI v2.0 -->" in current_html:
    current_html = current_html.replace("<!-- 12 — DETTAGLIO HUB GRAFICI v2.0 -->", mockups_html + "\n\n<!-- 13 — DETTAGLIO HUB GRAFICI v2.0 -->")
    
    # We should also update the section titles to avoid conflicts, or just leave them as 1. 2. 3. for the detailed pages, and the Hubs can be A, B, C or something. Let's make the Hubs 21, 22...
    # Actually the Hubs are "1. HUB SQUADRE E MATCH" etc. Let's make them 21. 22. 23.
    current_html = current_html.replace("1. HUB SQUADRE", "21. HUB SQUADRE")
    current_html = current_html.replace("2. HUB BIGLIETTERIA", "22. HUB BIGLIETTERIA")
    current_html = current_html.replace("3. HUB CORPORATE", "23. HUB CORPORATE")
    current_html = current_html.replace("4. HUB GIOVANILE", "24. HUB GIOVANILE")
    current_html = current_html.replace("5. HUB SOCIALE", "25. HUB SOCIALE")
    current_html = current_html.replace("6. HUB MEDIA", "26. HUB MEDIA")
    
    with open("proposta_tecnica_sdb.html", "w") as f:
        f.write(current_html)
    print("Fixed!")
else:
    print("Could not find marker")
