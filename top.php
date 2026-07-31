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

function jsonResponse($payload, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload);
    exit;
}

function nextCroppedDestination($directory) {
    for ($number = 1; $number < 100000; $number++) {
        $name = sprintf('cutted-%02d.jpg', $number);
        $path = $directory . DIRECTORY_SEPARATOR . $name;
        $notePath = $directory . DIRECTORY_SEPARATOR . 'NOTE-' . pathinfo($name, PATHINFO_FILENAME) . '.txt';
        if (!file_exists($path) && !file_exists($notePath)) {
            return [$path, $name];
        }
    }
    return [null, null];
}

function requestedCropNote($data) {
    $note = isset($data['note']) && is_string($data['note']) ? $data['note'] : '';
    if (strlen($note) > 1048576) {
        jsonResponse(['ok' => false, 'error' => 'Annotation is too large.'], 400);
    }
    return $note;
}

function writeCropNote($directory, $imageName, $note) {
    if (trim($note) === '') {
        return null;
    }
    $noteName = 'NOTE-' . pathinfo($imageName, PATHINFO_FILENAME) . '.txt';
    $notePath = $directory . DIRECTORY_SEPARATOR . $noteName;
    return @file_put_contents($notePath, $note, LOCK_EX) === false ? false : $noteName;
}

function createImageResource($path, $type) {
    switch ($type) {
        case IMAGETYPE_JPEG:
            return function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($path) : false;
        case IMAGETYPE_PNG:
            return function_exists('imagecreatefrompng') ? @imagecreatefrompng($path) : false;
        case IMAGETYPE_GIF:
            return function_exists('imagecreatefromgif') ? @imagecreatefromgif($path) : false;
        case IMAGETYPE_WEBP:
            return function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false;
        default:
            return false;
    }
}

$images = collectImages(__DIR__);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $isMultipart = isset($_POST['action']);
    if ($isMultipart) {
        $data = $_POST;
    } else {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            jsonResponse(['ok' => false, 'error' => 'Invalid request.'], 400);
        }
    }

    $action = isset($data['action']) ? $data['action'] : 'pick';
    $pick = isset($data['pick']) ? str_replace('\\', '/', $data['pick']) : '';
    if (!in_array($pick, $images, true)) {
        jsonResponse(['ok' => false, 'error' => 'Invalid image.'], 400);
    }

    $src = realpath(__DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $pick));
    $base = realpath(__DIR__);
    $basePrefix = $base . DIRECTORY_SEPARATOR;
    if ($src === false || $base === false || stripos($src, $basePrefix) !== 0) {
        jsonResponse(['ok' => false, 'error' => 'Invalid path.'], 400);
    }

    $handpicked = __DIR__ . DIRECTORY_SEPARATOR . 'top 20' . DIRECTORY_SEPARATOR . 'top 5' . DIRECTORY_SEPARATOR . 'handpicked';
    if (!is_dir($handpicked) && !mkdir($handpicked, 0777, true)) {
        jsonResponse(['ok' => false, 'error' => 'Could not create handpicked directory.'], 500);
    }

    if ($action === 'pick') {
        $dest = $handpicked . DIRECTORY_SEPARATOR . basename($pick);
        if (!copy($src, $dest)) {
            jsonResponse(['ok' => false, 'error' => 'Copy failed.'], 500);
        }
        jsonResponse(['ok' => true, 'file' => basename($dest)]);
    }

    if ($action === 'crop-upload') {
        if (!isset($_FILES['crop']) || $_FILES['crop']['error'] !== UPLOAD_ERR_OK) {
            jsonResponse(['ok' => false, 'error' => 'Fallback crop upload failed.'], 400);
        }
        $upload = $_FILES['crop']['tmp_name'];
        $uploadInfo = @getimagesize($upload);
        if ($uploadInfo === false || $uploadInfo[2] !== IMAGETYPE_JPEG) {
            jsonResponse(['ok' => false, 'error' => 'Fallback crop must be a JPG.'], 400);
        }
        $note = requestedCropNote($data);
        list($dest, $name) = nextCroppedDestination($handpicked);
        if ($dest === null || !move_uploaded_file($upload, $dest)) {
            jsonResponse(['ok' => false, 'error' => 'Could not save fallback crop.'], 500);
        }
        $noteName = writeCropNote($handpicked, $name, $note);
        if ($noteName === false) {
            @unlink($dest);
            jsonResponse(['ok' => false, 'error' => 'Could not save crop annotation.'], 500);
        }
        jsonResponse(['ok' => true, 'file' => $name, 'note' => $noteName, 'source' => 'browser']);
    }

    if ($action !== 'crop') {
        jsonResponse(['ok' => false, 'error' => 'Invalid action.'], 400);
    }

    $note = requestedCropNote($data);
    $crop = isset($data['crop']) && is_array($data['crop']) ? $data['crop'] : [];
    foreach (['x', 'y', 'width', 'height'] as $field) {
        if (!isset($crop[$field]) || !is_numeric($crop[$field])) {
            jsonResponse(['ok' => false, 'error' => 'Invalid crop coordinates.'], 400);
        }
    }

    $info = @getimagesize($src);
    if ($info === false || !function_exists('imagecreatetruecolor') || !function_exists('imagejpeg')) {
        jsonResponse(['ok' => false, 'error' => 'Original-image crop unavailable.', 'fallback' => true], 422);
    }

    $sourceWidth = (int) $info[0];
    $sourceHeight = (int) $info[1];
    $clientWidth = isset($crop['sourceWidth']) && is_numeric($crop['sourceWidth']) ? (int) $crop['sourceWidth'] : $sourceWidth;
    $clientHeight = isset($crop['sourceHeight']) && is_numeric($crop['sourceHeight']) ? (int) $crop['sourceHeight'] : $sourceHeight;
    if ($clientWidth !== $sourceWidth || $clientHeight !== $sourceHeight) {
        jsonResponse(['ok' => false, 'error' => 'Displayed image orientation differs from source.', 'fallback' => true], 422);
    }
    $x = max(0, min($sourceWidth - 1, (int) floor((float) $crop['x'])));
    $y = max(0, min($sourceHeight - 1, (int) floor((float) $crop['y'])));
    $width = max(1, min($sourceWidth - $x, (int) ceil((float) $crop['width'])));
    $height = max(1, min($sourceHeight - $y, (int) ceil((float) $crop['height'])));

    $sourceImage = createImageResource($src, $info[2]);
    if ($sourceImage === false) {
        jsonResponse(['ok' => false, 'error' => 'Could not decode original image.', 'fallback' => true], 422);
    }

    $croppedImage = @imagecreatetruecolor($width, $height);
    if ($croppedImage === false) {
        imagedestroy($sourceImage);
        jsonResponse(['ok' => false, 'error' => 'Could not allocate crop image.', 'fallback' => true], 422);
    }

    $white = imagecolorallocate($croppedImage, 255, 255, 255);
    imagefill($croppedImage, 0, 0, $white);
    $copied = imagecopy($croppedImage, $sourceImage, 0, 0, $x, $y, $width, $height);
    imagedestroy($sourceImage);
    if (!$copied) {
        imagedestroy($croppedImage);
        jsonResponse(['ok' => false, 'error' => 'Original-image crop failed.', 'fallback' => true], 422);
    }

    list($dest, $name) = nextCroppedDestination($handpicked);
    if ($dest === null) {
        imagedestroy($croppedImage);
        jsonResponse(['ok' => false, 'error' => 'No crop filename available.'], 500);
    }
    $saved = @imagejpeg($croppedImage, $dest, 95);
    imagedestroy($croppedImage);
    if (!$saved) {
        jsonResponse(['ok' => false, 'error' => 'Could not save cropped JPG.', 'fallback' => true], 500);
    }

    $noteName = writeCropNote($handpicked, $name, $note);
    if ($noteName === false) {
        @unlink($dest);
        jsonResponse(['ok' => false, 'error' => 'Could not save crop annotation.'], 500);
    }

    jsonResponse([
        'ok' => true,
        'file' => $name,
        'note' => $noteName,
        'source' => 'original',
        'crop' => ['x' => $x, 'y' => $y, 'width' => $width, 'height' => $height]
    ]);
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Top References</title>
<style>
:root { color-scheme: light; --ink: #111; --muted: #666; --ok: #00C853; --ok-rgb: 0, 200, 83; --crop: #FB043A; --crop-rgb: 251, 4, 58; }
* { box-sizing: border-box; }
body { margin: 0; font-family: Arial, Helvetica, sans-serif; color: var(--ink); background: #f3f4f6; }
header { position: sticky; top: 0; z-index: 20; display: none; align-items: center; justify-content: space-between; gap: 12px; padding: 10px 14px; background: rgba(255,255,255,.94); border-bottom: 1px solid #ddd; backdrop-filter: blur(8px); }
.title { min-width: 0; font-size: 14px; font-weight: 700; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.count { color: var(--muted); font-size: 13px; }
.crop-controls .count { min-width: 54px; color: #fff; text-align: center; }
main { min-height: 100vh; padding: 0 0 86px; }
.stage { width: 100%; display: flex; justify-content: center; }
.image-shell { position: relative; width: 100%; cursor: none; }
.image-shell.crop-has-selection { cursor: not-allowed; }
.stage img { width: 100%; height: auto; display: block; background: #fff; box-shadow: 0 1px 12px rgba(0,0,0,.12); -webkit-user-drag: none; user-select: none; }
.image-crosshair { position: absolute; inset: 0; z-index: 3; overflow: hidden; opacity: 0; pointer-events: none; }
.image-crosshair.visible { opacity: 1; }
.image-crosshair-svg { display: block; width: 100%; height: 100%; overflow: hidden; }
.image-crosshair-svg line { stroke-linecap: butt; vector-effect: non-scaling-stroke; shape-rendering: crispEdges; }
.image-crosshair-backdrop { opacity: .35; }
.image-crosshair-backdrop line { stroke: #000; stroke-width: 20; }
.image-crosshair-precision line { stroke: var(--crop); stroke-width: 3; }
.scissors-follower { position: fixed; left: 0; top: 0; z-index: 25; width: 50px; height: 50px; display: grid; place-items: center; border: 1px solid rgba(17,17,17,.12); border-radius: 50%; background: rgba(255,255,255,.96); box-shadow: 0 8px 24px rgba(0,0,0,.24); opacity: 0; pointer-events: none; transform: translate3d(-100px,-100px,0); transition: opacity .28s ease; will-change: transform; }
.scissors-follower.visible { opacity: 1; }
.scissors-follower-icon { display: block; width: 20px; height: 20px; background: #111; transform: scaleX(-1); -webkit-mask: url('https://cdn-icons-png.flaticon.com/512/542/542578.png') center / contain no-repeat; mask: url('https://cdn-icons-png.flaticon.com/512/542/542578.png') center / contain no-repeat; }
.empty { padding: 40px 18px; text-align: center; color: var(--muted); }
.controls, .crop-controls { position: fixed; bottom: 14px; z-index: 30; width: max-content; max-width: calc(100vw - 24px); display: flex; align-items: center; justify-content: center; gap: 18px; padding: 10px 14px; border-radius: 4px; background: rgba(17,17,17,.72); backdrop-filter: blur(10px); }
.controls { left: 50%; transform: translateX(-50%); }
.crop-controls { right: 14px; }
button { width: auto; min-width: 50px; height: 50px; padding: 0 20px; border: 0; border-radius: 4px; display: inline-flex; align-items: center; justify-content: center; gap: 8px; cursor: pointer; font-size: 23px; font-weight: 800; line-height: 1; box-shadow: 0 4px 16px rgba(0,0,0,.22); }
.nav { color: #111; background: #fff; }
.nav-icon { width: 18px; height: 18px; flex: 0 0 18px; object-fit: contain; pointer-events: none; }
.nav-icon.prev-icon { transform: rotate(180deg); }
.ok { color: #fff; background: var(--ok); font-size: 16px; font-weight: 600; letter-spacing: 0; }
.button-icon { width: 20px; height: 20px; flex: 0 0 20px; object-fit: contain; filter: invert(1); pointer-events: none; }
.crop-toggle { width: auto; min-width: 42px; height: 42px; color: #fff; background: var(--crop); border-radius: 4px; font-size: 16px; font-weight: 600; box-shadow: 0 2px 10px rgba(0,0,0,.18); }
.crop-toggle.active { background: var(--crop); outline: 3px solid rgba(var(--crop-rgb), .22); }
button:disabled { opacity: .45; cursor: default; }
.crop-point { position: absolute; z-index: 4; width: 14px; height: 14px; border: 2px solid #fff; border-radius: 50%; background: var(--crop); box-shadow: 0 0 0 2px var(--crop), 0 2px 8px rgba(0,0,0,.4); transform: translate(-50%, -50%); pointer-events: none; }
.crop-selection { position: absolute; z-index: 5; border: 2px solid #fff; outline: 2px solid var(--crop); background: rgba(var(--crop-rgb), .12); box-shadow: 0 0 0 99999px rgba(0,0,0,.72); cursor: move; touch-action: none; }
.crop-selection::after { content: ''; position: absolute; inset: 0; border: 1px dashed rgba(255,255,255,.82); pointer-events: none; }
.crop-save, .crop-cancel { position: absolute; right: 15px; z-index: 4; width: 40px; min-width: 40px; height: 40px; padding: 0; border-radius: 4px; box-shadow: 0 3px 12px rgba(0,0,0,.32); cursor: pointer; }
.crop-save { top: 50%; transform: translateY(-50%); background: var(--ok); }
.crop-cancel { top: 15px; background: var(--crop); }
.crop-selection.short-crop .crop-cancel { top: -55px; }
.crop-selection.short-crop .crop-save { top: 15px; transform: none; }
.crop-save-icon, .crop-cancel-icon { display: block; width: 22px; height: 22px; background: #fff; }
.crop-save-icon { -webkit-mask: url('https://cdn-icons-png.flaticon.com/512/542/542578.png') center / contain no-repeat; mask: url('https://cdn-icons-png.flaticon.com/512/542/542578.png') center / contain no-repeat; }
.crop-cancel-icon { -webkit-mask: url('https://cdn-icons-png.flaticon.com/512/8487/8487257.png') center / contain no-repeat; mask: url('https://cdn-icons-png.flaticon.com/512/8487/8487257.png') center / contain no-repeat; }
.crop-note { position: absolute; left: 15px; right: 15px; bottom: 15px; z-index: 3; width: calc(100% - 30px); min-height: 85px; height: clamp(85px, 30%, 110px); padding: 20px; border: 1px solid rgba(255,255,255,.42); border-radius: 4px; outline: 0; resize: vertical; color: #fff; caret-color: var(--ok); background: rgba(15,18,24,.86); backdrop-filter: blur(12px) saturate(120%); -webkit-backdrop-filter: blur(12px) saturate(120%); font: 500 16px/1.45 Arial, Helvetica, sans-serif; letter-spacing: .01em; box-shadow: inset 0 1px 0 rgba(255,255,255,.12), 0 8px 24px rgba(0,0,0,.38); scrollbar-color: rgba(255,255,255,.42) transparent; cursor: text; touch-action: auto; transition: border-color .16s ease, background .16s ease, box-shadow .16s ease; }
.crop-note:hover { border-color: rgba(255,255,255,.68); background: rgba(15,18,24,.91); }
.crop-note:focus { border-color: var(--ok); background: rgba(10,13,18,.96); box-shadow: inset 0 1px 0 rgba(255,255,255,.14), 0 0 0 3px rgba(var(--ok-rgb), .28), 0 10px 30px rgba(0,0,0,.46); }
.crop-note::placeholder { color: rgba(255,255,255,.62); opacity: 1; }
.crop-handle { position: absolute; z-index: 2; width: 16px; height: 16px; border: 2px solid #fff; border-radius: 50%; background: var(--crop); box-shadow: 0 1px 5px rgba(0,0,0,.4); }
.crop-handle[data-handle="nw"] { left: 0; top: 0; transform: translate(-50%, -50%); cursor: nwse-resize; }
.crop-handle[data-handle="ne"] { right: 0; top: 0; transform: translate(50%, -50%); cursor: nesw-resize; }
.crop-handle[data-handle="se"] { right: 0; bottom: 0; transform: translate(50%, 50%); cursor: nwse-resize; }
.crop-handle[data-handle="sw"] { left: 0; bottom: 0; transform: translate(-50%, 50%); cursor: nesw-resize; }
.toast { position: fixed; left: 50%; bottom: 82px; transform: translateX(-50%); z-index: 40; max-width: min(92vw, 560px); padding: 10px 14px; border-radius: 8px; color: #fff; background: rgba(17,17,17,.88); font-size: 14px; opacity: 0; pointer-events: none; transition: opacity .18s ease; }
.toast.show { opacity: 1; }
@media (pointer: coarse) { .scissors-follower { display: none; } }
@media (max-width: 700px) { main { padding-bottom: 170px; } .crop-controls { bottom: 98px; } }
@media (min-width: 1180px) { .image-shell { max-width: 1180px; } }
</style>
</head>
<body>
<header>
  <div class="title" id="title">Top References</div>
</header>
<main>
  <div class="stage" id="stage"></div>
</main>
<div class="controls">
  <button class="nav" id="prev" type="button" aria-label="Previous"><img class="nav-icon prev-icon" src="https://cdn-icons-png.flaticon.com/512/271/271228.png" alt="" aria-hidden="true"></button>
  <button class="ok" id="pick" type="button" aria-label="Copy current image to handpicked"><img class="button-icon" src="https://cdn-icons-png.flaticon.com/512/15219/15219750.png" alt="" aria-hidden="true">APPROVE</button>
  <button class="nav" id="next" type="button" aria-label="Next"><img class="nav-icon" src="https://cdn-icons-png.flaticon.com/512/271/271228.png" alt="" aria-hidden="true"></button>
</div>
<div class="crop-controls">
  <div class="count" id="count"></div>
  <button class="crop-toggle" id="cropToggle" type="button" aria-label="Enable crop selection" aria-pressed="false"><img class="button-icon" id="cropToggleIcon" src="https://cdn-icons-png.flaticon.com/512/542/542578.png" alt="" aria-hidden="true"><span id="cropToggleLabel">CUT</span></button>
</div>
<div class="scissors-follower" id="scissorsFollower" aria-hidden="true"><span class="scissors-follower-icon"></span></div>
<div class="toast" id="toast"></div>
<script>
const images = <?php echo json_encode($images, JSON_UNESCAPED_SLASHES); ?>;
const scissorsIconUrl = 'https://cdn-icons-png.flaticon.com/512/542/542578.png';
const cancelIconUrl = 'https://cdn-icons-png.flaticon.com/512/8487/8487257.png';
let index = 0;
const stage = document.getElementById('stage');
const title = document.getElementById('title');
const count = document.getElementById('count');
const prev = document.getElementById('prev');
const next = document.getElementById('next');
const pick = document.getElementById('pick');
const cropToggle = document.getElementById('cropToggle');
const scissorsFollower = document.getElementById('scissorsFollower');
const toast = document.getElementById('toast');
let toastTimer = null;
let cropMode = false;
let cropPoints = [];
let cropSelection = null;
let cropDrag = null;
let cropSaving = false;
let cropSession = 0;
let cropRequest = null;
let followerTargetX = 0;
let followerTargetY = 0;
let followerCurrentX = 0;
let followerCurrentY = 0;
let followerVisible = false;
let followerReady = false;
let followerFrame = null;

function showToast(message) {
  toast.textContent = message;
  toast.classList.add('show');
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => toast.classList.remove('show'), 1900);
}

function syncScissorsFollowerVisibility(distance) {
  const shouldShow = followerReady && distance > 30;
  if (shouldShow === followerVisible) return;
  followerVisible = shouldShow;
  scissorsFollower.classList.toggle('visible', shouldShow);
}

function animateScissorsFollower() {
  const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const ease = reducedMotion ? 1 : 0.05;
  followerCurrentX += (followerTargetX - followerCurrentX) * ease;
  followerCurrentY += (followerTargetY - followerCurrentY) * ease;
  scissorsFollower.style.transform = 'translate3d(' + (followerCurrentX - 25) + 'px,' + (followerCurrentY - 25) + 'px,0)';
  const distance = Math.hypot(followerTargetX - followerCurrentX, followerTargetY - followerCurrentY);
  syncScissorsFollowerVisibility(distance);
  if (distance > 0.1) {
    followerFrame = requestAnimationFrame(animateScissorsFollower);
  } else {
    followerFrame = null;
  }
}

function updateScissorsFollower(event) {
  if ((event.pointerType && event.pointerType !== 'mouse') || cropSelection) {
    hideScissorsFollower();
    return;
  }
  followerTargetX = event.clientX;
  followerTargetY = event.clientY;
  if (!followerReady) {
    followerCurrentX = followerTargetX;
    followerCurrentY = followerTargetY;
    followerReady = true;
  }
  const distance = Math.hypot(followerTargetX - followerCurrentX, followerTargetY - followerCurrentY);
  syncScissorsFollowerVisibility(distance);
  if (followerFrame === null && distance > 0.1) followerFrame = requestAnimationFrame(animateScissorsFollower);
}

function hideScissorsFollower() {
  followerVisible = false;
  followerReady = false;
  scissorsFollower.classList.remove('visible');
}

function updateImageCrosshair(event) {
  if ((event.pointerType && event.pointerType !== 'mouse') || cropSelection) {
    hideImageCrosshair();
    return;
  }
  const { shell, img } = currentParts();
  if (!shell || !img) return;
  const crosshair = shell.querySelector('.image-crosshair');
  if (!crosshair) return;
  const rect = img.getBoundingClientRect();
  const x = Math.max(0, Math.min(rect.width, event.clientX - rect.left));
  const y = Math.max(0, Math.min(rect.height, event.clientY - rect.top));
  crosshair.querySelectorAll('.vertical').forEach((line) => {
    line.setAttribute('x1', x);
    line.setAttribute('x2', x);
    line.setAttribute('y1', 0);
    line.setAttribute('y2', rect.height);
  });
  crosshair.querySelectorAll('.horizontal').forEach((line) => {
    line.setAttribute('x1', 0);
    line.setAttribute('x2', rect.width);
    line.setAttribute('y1', y);
    line.setAttribute('y2', y);
  });
  crosshair.classList.add('visible');
}

function hideImageCrosshair() {
  const crosshair = stage.querySelector('.image-crosshair');
  if (crosshair) crosshair.classList.remove('visible');
}

function currentParts() {
  return {
    shell: stage.querySelector('.image-shell'),
    img: stage.querySelector('img'),
    selection: stage.querySelector('.crop-selection')
  };
}

function clearCropVisuals() {
  stage.querySelectorAll('.crop-point, .crop-selection').forEach((node) => node.remove());
  const shell = stage.querySelector('.image-shell');
  if (shell) shell.classList.remove('crop-has-selection');
}

function setCropToggleState(showCancel) {
  const icon = document.getElementById('cropToggleIcon');
  const label = document.getElementById('cropToggleLabel');
  const nextSource = showCancel ? cancelIconUrl : scissorsIconUrl;
  if (icon && icon.getAttribute('src') !== nextSource) icon.setAttribute('src', nextSource);
  if (label) label.textContent = showCancel ? 'CANCEL' : 'CUT';
}

function setCropMode(enabled, message = '') {
  cropSession += 1;
  if (cropRequest) cropRequest.abort();
  cropRequest = null;
  cropSaving = false;
  cropMode = Boolean(enabled && images.length);
  cropPoints = [];
  cropSelection = null;
  cropDrag = null;
  clearCropVisuals();
  cropToggle.classList.toggle('active', cropMode);
  cropToggle.setAttribute('aria-pressed', cropMode ? 'true' : 'false');
  cropToggle.setAttribute('aria-label', cropMode ? 'Disable crop selection' : 'Enable crop selection');
  setCropToggleState(false);
  const { shell } = currentParts();
  if (shell) shell.classList.toggle('crop-enabled', cropMode);
  if (message) showToast(message);
}

function renderCropPoint(point) {
  const { shell } = currentParts();
  if (!shell) return;
  shell.querySelectorAll('.crop-point').forEach((node) => node.remove());
  const marker = document.createElement('div');
  marker.className = 'crop-point';
  marker.style.left = (point.x * 100) + '%';
  marker.style.top = (point.y * 100) + '%';
  shell.appendChild(marker);
  setCropToggleState(true);
}

function updateCropSelection() {
  if (!cropSelection) return;
  const { selection, img } = currentParts();
  if (!selection || !img) return;
  const selectionWidth = cropSelection.right - cropSelection.left;
  const selectionHeight = cropSelection.bottom - cropSelection.top;
  selection.style.left = (cropSelection.left * 100) + '%';
  selection.style.top = (cropSelection.top * 100) + '%';
  selection.style.width = (selectionWidth * 100) + '%';
  selection.style.height = (selectionHeight * 100) + '%';
  const renderedHeight = selectionHeight * img.getBoundingClientRect().height;
  selection.classList.toggle('short-crop', renderedHeight < 180);
}

function renderCropSelection() {
  const { shell } = currentParts();
  if (!shell || !cropSelection) return;
  hideScissorsFollower();
  hideImageCrosshair();
  shell.querySelectorAll('.crop-point, .crop-selection').forEach((node) => node.remove());
  const selection = document.createElement('div');
  selection.className = 'crop-selection';
  selection.setAttribute('role', 'region');
  selection.setAttribute('aria-label', 'Crop selection. Drag to move; drag corner handles to resize.');

  const saveButton = document.createElement('button');
  saveButton.className = 'crop-save';
  saveButton.type = 'button';
  saveButton.setAttribute('aria-label', 'Save selected crop');
  saveButton.innerHTML = '<span class="crop-save-icon" aria-hidden="true"></span>';
  saveButton.addEventListener('pointerdown', (event) => event.stopPropagation());
  saveButton.addEventListener('click', (event) => {
    event.preventDefault();
    event.stopPropagation();
    saveCrop();
  });
  selection.appendChild(saveButton);

  const cancelButton = document.createElement('button');
  cancelButton.className = 'crop-cancel';
  cancelButton.type = 'button';
  cancelButton.setAttribute('aria-label', 'Cancel crop selection');
  cancelButton.innerHTML = '<span class="crop-cancel-icon" aria-hidden="true"></span>';
  cancelButton.addEventListener('pointerdown', (event) => event.stopPropagation());
  cancelButton.addEventListener('click', (event) => {
    event.preventDefault();
    event.stopPropagation();
    setCropMode(false, 'Crop canceled.');
  });
  selection.appendChild(cancelButton);

  const note = document.createElement('textarea');
  note.className = 'crop-note';
  note.placeholder = 'Write Optional Note...';
  note.setAttribute('aria-label', 'Optional crop annotation');
  note.addEventListener('pointerdown', (event) => event.stopPropagation());
  note.addEventListener('click', (event) => event.stopPropagation());
  selection.appendChild(note);

  ['nw', 'ne', 'se', 'sw'].forEach((handle) => {
    const node = document.createElement('span');
    node.className = 'crop-handle';
    node.dataset.handle = handle;
    selection.appendChild(node);
  });
  selection.addEventListener('pointerdown', beginCropDrag);
  shell.appendChild(selection);
  shell.classList.add('crop-has-selection');
  updateCropSelection();
  setCropToggleState(true);
}

function pointFromEvent(event) {
  const { img } = currentParts();
  if (!img) return null;
  const rect = img.getBoundingClientRect();
  if (!rect.width || !rect.height) return null;
  const x = Math.max(0, Math.min(1, (event.clientX - rect.left) / rect.width));
  const y = Math.max(0, Math.min(1, (event.clientY - rect.top) / rect.height));
  return { x, y };
}

function handleCropClick(event) {
  if (event.button !== 0 || cropDrag) return;
  if (!cropMode) setCropMode(true);
  if (cropSelection) {
    if (!event.target.closest('.crop-selection')) setCropMode(false, 'Crop canceled.');
    return;
  }
  const point = pointFromEvent(event);
  if (!point) return;
  cropPoints.push(point);
  if (cropPoints.length === 1) {
    renderCropPoint(point);
    showToast('First point set. Click opposite corner.');
    return;
  }
  const first = cropPoints[0];
  cropSelection = {
    left: Math.min(first.x, point.x),
    top: Math.min(first.y, point.y),
    right: Math.max(first.x, point.x),
    bottom: Math.max(first.y, point.y)
  };
  cropPoints = [];
  if ((cropSelection.right - cropSelection.left) < 0.000001 || (cropSelection.bottom - cropSelection.top) < 0.000001) {
    cropSelection = null;
    clearCropVisuals();
    setCropToggleState(false);
    showToast('Crop needs width and height. Click two points again.');
    return;
  }
  renderCropSelection();
  showToast('Drag box/handles, add a note, then click scissors to save.');
}

function beginCropDrag(event) {
  if (event.button !== 0 || !cropMode || !cropSelection || cropSaving || event.target.closest('button, textarea')) return;
  event.preventDefault();
  event.stopPropagation();
  const handle = event.target.dataset.handle || 'move';
  cropDrag = {
    pointerId: event.pointerId,
    handle,
    startX: event.clientX,
    startY: event.clientY,
    start: { ...cropSelection }
  };
  event.currentTarget.setPointerCapture(event.pointerId);
}

function moveCropDrag(event) {
  if (!cropDrag || event.pointerId !== cropDrag.pointerId) return;
  const { img } = currentParts();
  if (!img) return;
  const rect = img.getBoundingClientRect();
  const dx = (event.clientX - cropDrag.startX) / rect.width;
  const dy = (event.clientY - cropDrag.startY) / rect.height;
  const start = cropDrag.start;
  const minX = Math.min(0.25, Math.max(1 / Math.max(1, img.naturalWidth), 8 / rect.width));
  const minY = Math.min(0.25, Math.max(1 / Math.max(1, img.naturalHeight), 8 / rect.height));

  if (cropDrag.handle === 'move') {
    const width = start.right - start.left;
    const height = start.bottom - start.top;
    const left = Math.max(0, Math.min(1 - width, start.left + dx));
    const top = Math.max(0, Math.min(1 - height, start.top + dy));
    cropSelection = { left, top, right: left + width, bottom: top + height };
  } else {
    cropSelection = { ...start };
    if (cropDrag.handle.includes('w')) cropSelection.left = Math.max(0, Math.min(start.right - minX, start.left + dx));
    if (cropDrag.handle.includes('e')) cropSelection.right = Math.min(1, Math.max(start.left + minX, start.right + dx));
    if (cropDrag.handle.includes('n')) cropSelection.top = Math.max(0, Math.min(start.bottom - minY, start.top + dy));
    if (cropDrag.handle.includes('s')) cropSelection.bottom = Math.min(1, Math.max(start.top + minY, start.bottom + dy));
  }
  updateCropSelection();
}

function endCropDrag(event) {
  if (!cropDrag || event.pointerId !== cropDrag.pointerId) return;
  cropDrag = null;
}

function cropPixels(img) {
  const naturalWidth = img.naturalWidth;
  const naturalHeight = img.naturalHeight;
  const x = Math.max(0, Math.min(naturalWidth - 1, Math.floor(cropSelection.left * naturalWidth)));
  const y = Math.max(0, Math.min(naturalHeight - 1, Math.floor(cropSelection.top * naturalHeight)));
  const right = Math.max(x + 1, Math.min(naturalWidth, Math.ceil(cropSelection.right * naturalWidth)));
  const bottom = Math.max(y + 1, Math.min(naturalHeight, Math.ceil(cropSelection.bottom * naturalHeight)));
  return {
    x,
    y,
    width: right - x,
    height: bottom - y,
    sourceWidth: naturalWidth,
    sourceHeight: naturalHeight
  };
}

async function readJson(response) {
  let data;
  try {
    data = await response.json();
  } catch (error) {
    throw new Error('Invalid server response.');
  }
  if (!response.ok || !data.ok) {
    const failure = new Error(data.error || 'Crop failed.');
    failure.fallback = Boolean(data.fallback);
    throw failure;
  }
  return data;
}

async function saveBrowserFallback(img, pixels, note, signal) {
  const canvas = document.createElement('canvas');
  canvas.width = pixels.width;
  canvas.height = pixels.height;
  const context = canvas.getContext('2d', { alpha: false });
  context.fillStyle = '#fff';
  context.fillRect(0, 0, canvas.width, canvas.height);
  context.drawImage(img, pixels.x, pixels.y, pixels.width, pixels.height, 0, 0, pixels.width, pixels.height);
  const blob = await new Promise((resolve, reject) => {
    canvas.toBlob((result) => result ? resolve(result) : reject(new Error('Browser crop failed.')), 'image/jpeg', 0.95);
  });
  const form = new FormData();
  form.append('action', 'crop-upload');
  form.append('pick', images[index]);
  form.append('note', note);
  form.append('crop', blob, 'crop.jpg');
  return readJson(await fetch(location.href, { method: 'POST', body: form, signal }));
}

async function saveCrop() {
  const { img, selection } = currentParts();
  if (!cropMode || !cropSelection || !img || !img.complete || !img.naturalWidth || cropSaving) return;
  const pixels = cropPixels(img);
  const note = selection ? selection.querySelector('.crop-note').value : '';
  const saveButton = selection ? selection.querySelector('.crop-save') : null;
  const session = cropSession;
  cropSaving = true;
  if (saveButton) saveButton.disabled = true;
  cropRequest = new AbortController();
  const signal = cropRequest.signal;
  try {
    let data;
    try {
      const response = await fetch(location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'crop', pick: images[index], crop: pixels, note }),
        signal
      });
      data = await readJson(response);
    } catch (error) {
      if (error.name === 'AbortError') throw error;
      if (!error.fallback) throw error;
      data = await saveBrowserFallback(img, pixels, note, signal);
    }
    if (session !== cropSession) return;
    const source = data.source === 'browser' ? 'browser fallback' : 'original image';
    const savedFiles = data.note ? data.file + ' + ' + data.note : data.file;
    setCropMode(false);
    showToast(savedFiles + ' saved from ' + source + '.');
  } catch (error) {
    if (error.name !== 'AbortError') alert(error.message || 'Crop failed.');
  } finally {
    if (session === cropSession) {
      cropSaving = false;
      cropRequest = null;
      if (saveButton && saveButton.isConnected) saveButton.disabled = false;
    }
  }
}

function render() {
  hideScissorsFollower();
  hideImageCrosshair();
  setCropMode(false);
  if (!images.length) {
    stage.innerHTML = '<div class="empty">No images found.</div>';
    title.textContent = 'Top References';
    count.textContent = '0 / 0';
    prev.disabled = true;
    next.disabled = true;
    pick.disabled = true;
    cropToggle.disabled = true;
    return;
  }
  const src = images[index];
  stage.innerHTML = '';
  const shell = document.createElement('div');
  shell.className = 'image-shell';
  const img = new Image();
  img.src = encodeURI(src);
  img.alt = src.split('/').pop();
  img.draggable = false;
  const crosshair = document.createElement('div');
  crosshair.className = 'image-crosshair';
  crosshair.setAttribute('aria-hidden', 'true');
  crosshair.innerHTML = '<svg class="image-crosshair-svg" xmlns="http://www.w3.org/2000/svg" focusable="false"><g class="image-crosshair-backdrop"><line class="vertical"></line><line class="horizontal"></line></g><g class="image-crosshair-precision"><line class="vertical"></line><line class="horizontal"></line></g></svg>';
  shell.appendChild(img);
  shell.appendChild(crosshair);
  shell.addEventListener('click', handleCropClick);
  shell.addEventListener('pointerenter', updateScissorsFollower);
  shell.addEventListener('pointerenter', updateImageCrosshair);
  shell.addEventListener('pointermove', updateScissorsFollower);
  shell.addEventListener('pointermove', updateImageCrosshair);
  shell.addEventListener('pointerleave', hideScissorsFollower);
  shell.addEventListener('pointerleave', hideImageCrosshair);
  stage.appendChild(shell);
  title.textContent = src.split('/').pop();
  count.textContent = (index + 1) + ' / ' + images.length;
  prev.disabled = index === 0;
  next.disabled = index === images.length - 1;
  pick.disabled = false;
  cropToggle.disabled = false;
}

function resetTop() { window.scrollTo({ top: 0, behavior: 'instant' }); }

cropToggle.addEventListener('click', () => {
  const enable = !cropMode;
  setCropMode(enable, enable ? 'Crop enabled. Click two image points.' : 'Crop canceled.');
});

prev.addEventListener('click', () => {
  if (index === 0) { alert('Start of list.'); return; }
  index -= 1;
  resetTop();
  render();
});

next.addEventListener('click', () => {
  if (index === images.length - 1) { alert('End of list.'); return; }
  index += 1;
  resetTop();
  render();
});

pick.addEventListener('click', async () => {
  if (!images.length) return;
  resetTop();
  pick.disabled = true;
  try {
    const res = await fetch(location.href, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'pick', pick: images[index] })
    });
    const data = await readJson(res);
    showToast('Copied to handpicked.');
  } catch (error) {
    alert(error.message || 'Copy failed.');
  } finally {
    pick.disabled = false;
  }
});

document.addEventListener('pointermove', moveCropDrag);
document.addEventListener('pointerup', endCropDrag);
document.addEventListener('pointercancel', endCropDrag);

window.addEventListener('resize', updateCropSelection);

render();
</script>
</body>
</html>
