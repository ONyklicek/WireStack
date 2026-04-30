# FileUpload

File upload with preview, validation, and image processing.

```php
use NyonCode\WireForms\Components\FileUpload;
```

## Basic Usage

```php
FileUpload::make('attachment')
    ->disk('public')
    ->directory('attachments')
```

## Validation

```php
FileUpload::make('document')
    ->acceptedFileTypes(['application/pdf', 'image/*'])
    ->maxSize(10240)       // KB
    ->minSize(100)         // KB
    ->multiple()
    ->maxFiles(5)
    ->minFiles(1)
```

## Image Mode

```php
FileUpload::make('photo')
    ->image()
    ->imageResizeTargetWidth(1920)
    ->imageResizeTargetHeight(1080)
    ->imageCropAspectRatio('16:9')
```

## Avatar

```php
FileUpload::make('avatar')
    ->avatar()             // circular preview, single file
```

## Storage

```php
FileUpload::make('file')
    ->disk('s3')
    ->directory('uploads/2024')
    ->visibility('public')
    ->preserveFilenames()
```

## Methods

| Method | Description |
|--------|-------------|
| `disk(string)` | Storage disk |
| `directory(string)` | Upload directory |
| `visibility(string)` | File visibility (`public`, `private`) |
| `preserveFilenames()` | Keep original filenames |
| `acceptedFileTypes(array)` | Allowed MIME types |
| `maxSize(int)` | Max file size in KB |
| `minSize(int)` | Min file size in KB |
| `multiple()` | Allow multiple files |
| `maxFiles(int)` | Max number of files |
| `minFiles(int)` | Min number of files |
| `image()` | Image-only mode |
| `avatar()` | Avatar mode (circular, single) |
| `imageResizeTargetWidth(int)` | Resize width |
| `imageResizeTargetHeight(int)` | Resize height |
| `imageCropAspectRatio(string)` | Crop aspect ratio |
