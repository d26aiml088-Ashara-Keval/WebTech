<?php
declare(strict_types=1);

function studentHubValidateEvent(array $input): array
{
    $values = [];

    foreach (['title', 'category', 'event_date', 'location', 'description'] as $field) {
        $value = $input[$field] ?? '';
        $values[$field] = is_string($value) ? trim($value) : '';
    }

    $errors = [];
    $titleLength = preg_match_all('/./us', $values['title']);
    $categoryLength = preg_match_all('/./us', $values['category']);
    $locationLength = preg_match_all('/./us', $values['location']);
    $descriptionLength = preg_match_all('/./us', $values['description']);

    if ($titleLength === false || $titleLength < 3 || $titleLength > 160) {
        $errors['title'] = 'Use an event title between 3 and 160 characters.';
    }

    if ($categoryLength === false || $categoryLength < 2 || $categoryLength > 60) {
        $errors['category'] = 'Use a category between 2 and 60 characters.';
    }

    if (
        !preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $values['event_date'])
        || !checkdate(
            (int) substr($values['event_date'], 5, 2),
            (int) substr($values['event_date'], 8, 2),
            (int) substr($values['event_date'], 0, 4)
        )
    ) {
        $errors['event_date'] = 'Choose a valid event date.';
    }

    if ($locationLength === false || $locationLength < 2 || $locationLength > 160) {
        $errors['location'] = 'Use a location between 2 and 160 characters.';
    }

    if ($descriptionLength === false || $descriptionLength < 10 || $descriptionLength > 3000) {
        $errors['description'] = 'Write a description between 10 and 3,000 characters.';
    }

    return [$values, $errors];
}

function studentHubSavePoster(?array $upload): ?string
{
    if ($upload === null || ($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    $error = $upload['error'] ?? null;
    $temporaryPath = $upload['tmp_name'] ?? null;
    $size = $upload['size'] ?? null;

    if ($error !== UPLOAD_ERR_OK || !is_string($temporaryPath) || !is_int($size)) {
        throw new InvalidArgumentException('The poster upload did not complete successfully.');
    }

    if ($size < 1 || $size > 2 * 1024 * 1024) {
        throw new InvalidArgumentException('Choose a poster image smaller than 2 MB.');
    }

    if (!is_uploaded_file($temporaryPath)) {
        throw new InvalidArgumentException('The uploaded poster could not be verified.');
    }

    if (!class_exists(finfo::class)) {
        throw new RuntimeException('The PHP fileinfo extension must be enabled to validate poster uploads.');
    }

    $fileInfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $fileInfo->file($temporaryPath);
    $allowedTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
    $imageDetails = getimagesize($temporaryPath);

    if (
        !is_string($mimeType)
        || !isset($allowedTypes[$mimeType])
        || $imageDetails === false
        || ($imageDetails['mime'] ?? '') !== $mimeType
    ) {
        throw new InvalidArgumentException('Use a valid JPEG, PNG, or WebP image for the event poster.');
    }

    $directory = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'posters';
    if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
        throw new RuntimeException('The event poster directory is not writable.');
    }

    $fileName = bin2hex(random_bytes(16)) . '.' . $allowedTypes[$mimeType];
    if (!move_uploaded_file($temporaryPath, $directory . DIRECTORY_SEPARATOR . $fileName)) {
        throw new RuntimeException('The validated poster could not be stored.');
    }

    return 'uploads/posters/' . $fileName;
}

function studentHubDeletePoster(?string $relativePath): void
{
    if (
        $relativePath === null
        || !preg_match('#\Auploads/posters/[a-f0-9]{32}\.(?:jpg|png|webp)\z#', $relativePath)
    ) {
        return;
    }

    $path = __DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    if (is_file($path) && !unlink($path)) {
        error_log('StudentHub could not remove an unused event poster: ' . $path);
    }
}
