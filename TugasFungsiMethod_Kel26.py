# === Sistem Pemesanan Buku (Kelompok 26) ===

# Function non-return type (tanpa parameter)
def awalProgram():
    print("=== Sistem Pemesanan Buku (Kelompok 26) ===\n")

awalProgram()

# Function return type (dengan parameter)
def hitungTotal(harga, jumlah):
    return harga * jumlah


# Class dengan method
class Perpustakaan:
    def __init__(self):
        self.buku = [
            {"judul": "Pengenalan Jaringan untuk Pemula", "harga": 65000},
            {"judul": "Belajar Bahasa Python for Expert", "harga": 55000},
            {"judul": "Sistem Algoritma & Kecerdasan Buatan", "harga": 75000}
        ]

    # Method non-return type
    def tampilkanBuku(self):
        for i, b in enumerate(self.buku, start=1):
            print(f"{i}. {b['judul']} - Rp {b['harga']}")

    # Method return type
    def pilih(self, no):
        if 1 <= no <= len(self.buku):
            return self.buku[no - 1]
        else:
            return None


# Program utama
perpus = Perpustakaan()
perpus.tampilkanBuku()

while True:
    try:
        no = int(input("\nPilih nomor buku: "))
    except ValueError:
        print("Input harus berupa angka!")
        continue

    buku = perpus.pilih(no)

    if buku:
        try:
            jml = int(input("Jumlah: "))
        except ValueError:
            print("Jumlah harus berupa angka!")
            continue

        total = hitungTotal(buku["harga"], jml)

        # satu kondisi untuk semua jenis diskon
        if jml >= 10:
            print(f"Anda membeli {jml} buku, dapat diskon 20%")
            total *= 0.8
        elif total > 120000:
            print("Dapat diskon 10%!")
            total *= 0.9
        else:
            print("Belum memenuhi syarat diskon")

        print(f"Total: Rp {int(total)}")

        lagi = input("\nMau beli lagi? (y/n): ").lower()
        if lagi != 'y':
            break
    else:
        print("Nomor tidak valid!")

print("\nTerima kasih sudah membeli buku di toko kami!")
