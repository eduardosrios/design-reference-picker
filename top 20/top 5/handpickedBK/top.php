<?php
$allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

function collectImages($dir, $prefix = '') {
    global $allowedExtensions;
    $items = [];
    if (!is_dir($dir)) {
        return $items;
    }
    $entries = scandir($dir);
    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..' || $entry === 'handpicked' || $entry === 'top.php') {
            continue;
        }
        $full = $dir . DIRECTORY_SEPARATOR . $entry;
        $rel = $prefix === '' ? $entry : $prefix . '/' . $entry;
        if (is_dir($full) && ($entry === 'top 20' || $entry === 'top 5')) {
            $items = array_merge($items, collectImages($full, $rel));
            continue;
        }
        if (is_file($full)) {
            $ext = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
            if (in_array($ext, $allowedExtensions, true)) {
                $items[] = $rel;
            }
        }
    }
    natcasesort($items);
    return array_values($items);
}

$images = collectImages(__DIR__);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    $pick = isset($data['pick']) ? str_replace('\\', '/', $data['pick']) : '';
    if (!in_array($pick, $images, true)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Invalid image.']);
        exit;
    }
    $src = realpath(__DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $pick));
    $base = realpath(__DIR__);
    if ($src === false || strpos($src, $base) !== 0) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Invalid path.']);
        exit;
    }
    $handpicked = __DIR__ . DIRECTORY_SEPARATOR . 'top 20' . DIRECTORY_SEPARATOR . 'top 5' . DIRECTORY_SEPARATOR . 'handpicked';
    if (!is_dir($handpicked)) {
        mkdir($handpicked, 0777, true);
    }
    $dest = $handpicked . DIRECTORY_SEPARATOR . basename($pick);
    if (!copy($src, $dest)) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Copy failed.']);
        exit;
    }
    echo json_encode(['ok' => true, 'file' => basename($dest)]);
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Top References</title>
<style>
:root { color-scheme: light; --ink: #111; --muted: #666; --ok: #16a34a; }
* { box-sizing: border-box; }
body { margin: 0; font-family: Arial, Helvetica, sans-serif; color: var(--ink); background: #f3f4f6; }
header { position: sticky; top: 0; z-index: 5; display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 10px 14px; background: rgba(255,255,255,.94); border-bottom: 1px solid #ddd; backdrop-filter: blur(8px); }
.title { min-width: 0; font-size: 14px; font-weight: 700; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.count { flex: 0 0 auto; color: var(--muted); font-size: 13px; }
main { min-height: 100vh; padding: 0 0 86px; }
.stage { width: 100%; display: flex; justify-content: center; }
.stage img { width: 100%; height: auto; display: block; background: #fff; box-shadow: 0 1px 12px rgba(0,0,0,.12); }
.empty { padding: 40px 18px; text-align: center; color: var(--muted); }
.controls { position: fixed; left: 0; right: 0; bottom: 0; z-index: 10; display: flex; align-items: center; justify-content: center; gap: 18px; padding: 14px 18px; background: rgba(17,17,17,.72); backdrop-filter: blur(10px); }
button { width: 50px; height: 50px; border: 0; border-radius: 999px; display: inline-flex; align-items: center; justify-content: center; cursor: pointer; font-size: 23px; font-weight: 800; line-height: 1; box-shadow: 0 4px 16px rgba(0,0,0,.22); }
.nav { color: #111; background: #fff; }
.ok { color: #fff; background: var(--ok); font-size: 13px; letter-spacing: 0; }
button:disabled { opacity: .45; cursor: default; }
.toast { position: fixed; left: 50%; bottom: 82px; transform: translateX(-50%); z-index: 11; max-width: min(92vw, 560px); padding: 10px 14px; border-radius: 8px; color: #fff; background: rgba(17,17,17,.88); font-size: 14px; opacity: 0; pointer-events: none; transition: opacity .18s ease; }
.toast.show { opacity: 1; }
@media (min-width: 1180px) { .stage img { max-width: 1180px; } }
</style>
</head>
<body>
<header>
  <div class="title" id="title">Top References</div>
  <div class="count" id="count"></div>
</header>
<main>
  <div class="stage" id="stage"></div>
</main>
<div class="controls">
  <button class="nav" id="prev" type="button" aria-label="Previous">&larr;</button>
  <button class="ok" id="pick" type="button" aria-label="Copy current image to handpicked">OK</button>
  <button class="nav" id="next" type="button" aria-label="Next">&rarr;</button>
</div>
<div class="toast" id="toast"></div>
<script>
const images = <?php echo json_encode($images, JSON_UNESCAPED_SLASHES); ?>;
let index = 0;
const stage = document.getElementById('stage');
const title = document.getElementById('title');
const count = document.getElementById('count');
const prev = document.getElementById('prev');
const next = document.getElementById('next');
const pick = document.getElementById('pick');
const toast = document.getElementById('toast');
let toastTimer = null;

function showToast(message) {
  toast.textContent = message;
  toast.classList.add('show');
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => toast.classList.remove('show'), 1600);
}

function render() {
  if (!images.length) {
    stage.innerHTML = '<div class="empty">No images found.</div>';
    title.textContent = 'Top References';
    count.textContent = '0 / 0';
    prev.disabled = true;
    next.disabled = true;
    pick.disabled = true;
    return;
  }
  const src = images[index];
  stage.innerHTML = '';
  const img = new Image();
  img.src = encodeURI(src);
  img.alt = src.split('/').pop();
  stage.appendChild(img);
  title.textContent = src.split('/').pop();
  count.textContent = (index + 1) + ' / ' + images.length;
  prev.disabled = index === 0;
  next.disabled = index === images.length - 1;
}

function resetTop() { window.scrollTo({ top: 0, behavior: 'instant' }); }
prev.addEventListener('click', () => {
  if (index === 0) { alert('Start of list.'); return; }
  index -= 1; resetTop(); render();
});
next.addEventListener('click', () => {
  if (index === images.length - 1) { alert('End of list.'); return; }
  index += 1; resetTop(); render();
});
pick.addEventListener('click', async () => {
  if (!images.length) return;
  resetTop();
  pick.disabled = true;
  try {
    const res = await fetch(location.href, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ pick: images[index] }) });
    const data = await res.json();
    if (!res.ok || !data.ok) throw new Error(data.error || 'Copy failed.');
    showToast('Copied to handpicked.');
  } catch (err) {
    alert(err.message || 'Copy failed.');
  } finally {
    pick.disabled = false;
  }
});
render();
</script>
</body>
</html>
