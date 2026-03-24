const fs = require('fs');
const path = require('path');

function walk(dir) {
  let results = [];
  const list = fs.readdirSync(dir);
  list.forEach(file => {
    file = path.join(dir, file);
    const stat = fs.statSync(file);
    if (stat && stat.isDirectory()) {
      results = results.concat(walk(file));
    } else if (file.endsWith('.vue')) {
      results.push(file);
    }
  });
  return results;
}

const files = walk('./src/views/ERP');
let updatedCount = 0;

files.forEach(file => {
  // Skip the layout files themselves since they SHOULD have DynamicSidebar
  if (file.endsWith('Layout.vue')) return;

  let content = fs.readFileSync(file, 'utf8');
  let original = content;

  // Remove Sidebar components from template
  content = content.replace(/<[A-Za-z]+Sidebar\s+v-if="[^"]+"\s*\/>/g, '');
  content = content.replace(/<[A-Za-z]+Sidebar\s+v-else\s*\/>/g, '');
  content = content.replace(/<[A-Za-z]+Sidebar\s*\/>/g, '');
  content = content.replace(/<SupplyChainSider\s*\/>/g, '');

  content = content.replace(/import\s+[A-Za-z]+Sidebar\s+from\s+["'][\.\/]+layouts\/Sidebar\/[A-Za-z]+Sidebar\.vue["'];?/g, '');
  content = content.replace(/import\s+SupplyChainSider\s+from\s+["'][\.\/]+layouts\/Sidebar\/SupplyChainSidebar\.vue["'];?/g, '');

  if (content !== original) {
    fs.writeFileSync(file, content);
    console.log(`Updated ${file}`);
    updatedCount++;
  }
});

console.log(`Total files updated: ${updatedCount}`);
