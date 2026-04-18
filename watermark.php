<?php
// watermark.php - (Royal Protector V1.0)
// يقوم بوضع ختم مائي على الصور ديناميكياً لحمايتها من السرقة

if (!isset($_GET['src']) || empty($_GET['src'])) exit;

$file_path = $_GET['src'];
$real_path = realpath($file_path);

// 1. التحقق من أمان الملف (عشان محدش يطلب ملفات النظام)
if (!$real_path || strpos($real_path, realpath('uploads')) === false || !file_exists($real_path)) {
    // لو الملف مش موجود او خارج مجلد uploads، نرجع صورة فاضية
    header("Content-Type: image/png");
    $im = @imagecreate(1, 1); 
    imagecolorallocate($im, 255, 255, 255); 
    imagepng($im); 
    imagedestroy($im);
    exit;
}

// 2. تحديد نوع الصورة
$ext = strtolower(pathinfo($real_path, PATHINFO_EXTENSION));
switch ($ext) {
    case 'jpg': case 'jpeg': $image = imagecreatefromjpeg($real_path); header("Content-Type: image/jpeg"); break;
    case 'png': $image = imagecreatefrompng($real_path); header("Content-Type: image/png"); break;
    case 'webp': $image = imagecreatefromwebp($real_path); header("Content-Type: image/webp"); break;
    default: exit;
}

// 3. إعدادات الختم
$text = "Arab Eagles - PREVIEW"; // النص الذي سيظهر
$font_size = 5; // حجم الخط الافتراضي (للموبايل)
$width = imagesx($image);
$height = imagesy($image);

// لون الختم (أبيض شفاف)
$white = imagecolorallocatealpha($image, 255, 255, 255, 80); // 80 = شفافية عالية
$grey = imagecolorallocatealpha($image, 128, 128, 128, 90);

// 4. تكرار الختم على كامل الصورة (Pattern)
// عشان لو قص حتة من الصورة، الختم يفضل موجود في الباقي
$step_x = 300; 
$step_y = 300;

for ($x = 0; $x < $width; $x += $step_x) {
    for ($y = 0; $y < $height; $y += $step_y) {
        // كتابة النص بشكل مائل ومكرر
        // imagestring لا يدعم الميلان والدوران، لو السيرفر يدعم imagettftext يكون أفضل، بس ده حل يعمل على كل السيرفرات
        imagestring($image, 5, $x, $y, $text, $grey);
        imagestring($image, 5, $x+2, $y+2, $text, $white); // ظل خفيف
    }
}

// إضافة خطين متقاطعين (Cross) لمزيد من الحماية
imageline($image, 0, 0, $width, $height, $white);
imageline($image, $width, 0, 0, $height, $white);

// 5. إخراج الصورة
if ($ext == 'png') {
    imagepng($image);
} elseif ($ext == 'webp') {
    imagewebp($image, null, 80); // جودة 80% للتصفح السريع
} else {
    imagejpeg($image, null, 80);
}

imagedestroy($image);
?>