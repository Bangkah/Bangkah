# Bangkah Laravel Starter Kit

## Artisan Command Not Found Troubleshooting

Jika setelah install package ini perintah `php artisan bangkah:create` tidak muncul, lakukan langkah berikut:

1. Jalankan perintah berikut di root project Laravel Anda:
   
	```sh
	composer dump-autoload
	php artisan config:clear
	php artisan cache:clear
	php artisan config:cache
	```

2. Pastikan file `config/app.php` sudah memuat ServiceProvider ini jika tidak autodiscover:
   
	```php
	'providers' => [
		 // ...
		 Bangkah\Starter\BangkahServiceProvider::class,
	],
	```

3. Pastikan Anda menjalankan perintah:
   
	```sh
	php artisan bangkah:create
	```

Jika masih bermasalah, pastikan tidak ada cache composer/laravel yang tertinggal dan package sudah versi terbaru.

---
## Penggunaan

Setelah install, jalankan:

```sh
php artisan bangkah:create
```

Ikuti wizard interaktif untuk scaffold project Laravel Anda.
# Bangkah Starter Kit

Laravel starter kit package untuk scaffolding project dengan cepat.

## Installation

```bash
composer require bangkah/bangkah
```

## Usage

```bash
php artisan bangkah:create
```

## Documentation

Lihat [dokumentasi lengkap](../../README.md) untuk panduan detail.

## License

MIT
