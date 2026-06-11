<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::unprepared('DROP TRIGGER IF EXISTS cek_stok_produk');
        DB::unprepared('DROP TRIGGER IF EXISTS update_stok_produk');
        DB::unprepared('DROP TRIGGER IF EXISTS update_waktu_pembayaran');

        DB::unprepared(<<<'SQL'
CREATE TRIGGER cek_stok_produk
BEFORE INSERT ON detail_pesanan
FOR EACH ROW
BEGIN
    DECLARE stok_saat_ini INT;

    SELECT stok INTO stok_saat_ini
    FROM produk
    WHERE id_produk = NEW.id_produk;

    IF stok_saat_ini < NEW.jumlah THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Stok produk tidak mencukupi!';
    END IF;
END
SQL);

        DB::unprepared(<<<'SQL'
CREATE TRIGGER update_stok_produk
AFTER INSERT ON detail_pesanan
FOR EACH ROW
BEGIN
    UPDATE produk
    SET stok = stok - NEW.jumlah
    WHERE id_produk = NEW.id_produk;
END
SQL);

        DB::unprepared(<<<'SQL'
CREATE TRIGGER update_waktu_pembayaran
BEFORE UPDATE ON pesanan
FOR EACH ROW
BEGIN
    IF NEW.status_pembayaran = 'lunas' AND OLD.status_pembayaran <> 'lunas' THEN
        SET NEW.waktu_pembayaran = NOW();
    END IF;
END
SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::unprepared('DROP TRIGGER IF EXISTS cek_stok_produk');
        DB::unprepared('DROP TRIGGER IF EXISTS update_stok_produk');
        DB::unprepared('DROP TRIGGER IF EXISTS update_waktu_pembayaran');
    }
};