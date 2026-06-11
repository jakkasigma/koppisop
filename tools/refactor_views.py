import os
import re

views_dir = r"d:\psrnl\laravel\kasir\resources\views"
target_dirs = [
    'dashboard', 'diskon', 'karyawan', 'kategori', 'master', 
    'master-opsi-kasir', 'pelanggan', 'produk', 'promo-bundling', 
    'struk-setting', 'transaksi'
]

# Skip these files inside the target directories (if they are completely different)
exclude_files = ['index.blade.php', 'statistik.blade.php', 'keuangan.blade.php']

def process_file(filepath):
    with open(filepath, 'r', encoding='utf-8') as f:
        content = f.read()

    original = content
    filename = os.path.basename(filepath)

    # Replace <div class="nav"> @include('partials.top-nav-links') </div>
    content = re.sub(r'<div class="nav">\s*@include\(\'partials\.top-nav-links\'\)\s*</div>', '', content)
    content = re.sub(r'@include\(\'partials\.top-nav-links\'\)', '', content)
    
    # Extract hero content and replace with admin-page-head
    # We look for <div class="hero"> ... </div>
    # A simple regex won't match nested divs well, but usually hero just has h1, p, and some direct children.
    # Let's find the hero block manually
    if '<div class="hero">' in content:
        # Simple replacement for hero opening
        # We need to wrap its contents in an extra <div> for the new layout
        content = content.replace('<div class="hero">', '<div class="admin-page-head">\n    <div>')
        # This will leave the closing </div> of hero as is, which perfectly matches the extra <div> we opened!
        # Wait, the structure of admin-page-head is:
        # <div class="admin-page-head">
        #     <div>
        #         <h1>Title</h1>
        #         <p>Sub</p>
        #     </div>
        #     <div class="admin-page-actions">...</div> <!-- optional -->
        # </div>
        #
        # If we replace <div class="hero"> with <div class="admin-page-head">\n<div>, we are adding one opening tag.
        # So we need to add one closing tag </div> right before the hero's closing </div>.
        
        # To do this safely, we find the hero bounds.
        # Actually, if we just do:
        # <div class="hero"> -> <div class="admin-page-head">
        # It's almost the same, but it doesn't have the inner <div>. 
        # The inner <div> is used in index.blade.php because there's a right side .admin-page-actions.
        # If there is no right side, <div class="admin-page-head"> directly containing h1 and p is totally fine and will style perfectly!
        content = content.replace('<div class="hero">', '<div class="admin-page-head">')
        content = content.replace('<div class="hero-head">', '<div class="admin-page-head">')

    # Fix cards globally
    # Replace class="card" or class="card " with class="admin-soft-card" or class="admin-soft-card "
    content = re.sub(r'class="card\b', 'class="admin-soft-card', content)
    
    # Fix grids
    # Replace class="grid" with class="admin-grid-secondary"
    content = re.sub(r'class="grid\b', 'class="admin-grid-secondary', content)

    if content != original:
        with open(filepath, 'w', encoding='utf-8') as f:
            f.write(content)
        print(f"Updated: {filepath}")

for root, dirs, files in os.walk(views_dir):
    # Only process files if they are in the target_dirs
    parts = root.replace(views_dir, '').strip(os.sep).split(os.sep)
    if parts[0] in target_dirs:
        for file in files:
            if file.endswith('.blade.php'):
                # Skip the files we already carefully manually updated
                if parts[0] == 'dashboard' and file in exclude_files:
                    continue
                filepath = os.path.join(root, file)
                process_file(filepath)

print("Refactor complete.")
