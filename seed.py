"""Seed the database with demo data for local testing/demonstration."""

from datetime import datetime

from app import create_app
from app.extensions import db
from app.models import (
    BobotKPI,
    JenisPekerjaan,
    Pegawai,
    PelaksanaanLapangan,
    Penugasan,
    SettingInsentif,
    TargetPeriode,
    User,
    Verifikasi,
    WajibPajak,
    Wilayah,
)


def seed_data():
    """Insert demo data. Assumes it is called within an active app context."""
    db.create_all()

    if User.query.first() is not None:
        print("Database sudah memiliki data. Seeding dilewati.")
        return

    print("Seeding data awal...")

    # --- Users ---
    admin = User(username="admin", nama_lengkap="Administrator", role="admin")
    admin.set_password("admin123")
    db.session.add(admin)

    petugas_users = []
    for i in range(1, 4):
        u = User(
            username=f"petugas{i}",
            nama_lengkap=f"Petugas Penagihan {i}",
            role="petugas",
        )
        u.set_password("petugas123")
        db.session.add(u)
        petugas_users.append(u)

    db.session.flush()

    # --- Wilayah ---
    wilayah_data = [
        ("W01", "Jakarta Pusat"),
        ("W02", "Jakarta Selatan"),
        ("W03", "Bandung"),
    ]
    wilayah_list = []
    for kode, nama in wilayah_data:
        w = Wilayah(kode=kode, nama_wilayah=nama)
        db.session.add(w)
        wilayah_list.append(w)
    db.session.flush()

    # --- Pegawai ---
    pegawai_data = [
        ("196501011990031001", "Budi Santoso", "Petugas Penagihan Pajak", 0),
        ("197002022001032002", "Siti Aminah", "Petugas Penagihan Pajak", 1),
        ("198003032005011003", "Ahmad Fauzi", "Petugas Penagihan Pajak", 2),
    ]
    pegawai_list = []
    for idx, (nip, nama, jabatan, w_idx) in enumerate(pegawai_data):
        p = Pegawai(
            nip=nip,
            nama=nama,
            jabatan=jabatan,
            wilayah_id=wilayah_list[w_idx].id,
            user_id=petugas_users[idx].id,
            aktif=True,
        )
        db.session.add(p)
        pegawai_list.append(p)
    db.session.flush()

    # --- Wajib Pajak ---
    wp_data = [
        ("01.234.567.8-001.000", "PT Sejahtera Abadi", "Jl. Sudirman No. 1, Jakarta", 0, "PPh Badan"),
        ("01.234.567.8-002.000", "CV Maju Jaya", "Jl. Gatot Subroto No. 12, Jakarta", 1, "PPN"),
        ("01.234.567.8-003.000", "PT Bumi Persada", "Jl. Asia Afrika No. 5, Bandung", 2, "PBB"),
        ("01.234.567.8-004.000", "Toko Makmur Sentosa", "Jl. Thamrin No. 8, Jakarta", 0, "PPh Orang Pribadi"),
        ("01.234.567.8-005.000", "PT Cahaya Nusantara", "Jl. Braga No. 20, Bandung", 2, "PPN"),
    ]
    wp_list = []
    for npwp, nama, alamat, w_idx, jenis in wp_data:
        wp = WajibPajak(
            npwp=npwp,
            nama=nama,
            alamat=alamat,
            wilayah_id=wilayah_list[w_idx].id,
            jenis_pajak=jenis,
        )
        db.session.add(wp)
        wp_list.append(wp)
    db.session.flush()

    # --- Jenis Pekerjaan ---
    jp_data = [
        ("Penagihan Tunggakan Pajak", "Penagihan langsung ke wajib pajak yang menunggak", "kasus"),
        ("Verifikasi Lapangan", "Kunjungan verifikasi data wajib pajak", "kunjungan"),
        ("Sosialisasi Kepatuhan Pajak", "Edukasi kepatuhan pembayaran pajak", "kegiatan"),
    ]
    jp_list = []
    for nama, deskripsi, satuan in jp_data:
        jp = JenisPekerjaan(nama=nama, deskripsi=deskripsi, satuan=satuan)
        db.session.add(jp)
        jp_list.append(jp)
    db.session.flush()

    # --- Bobot KPI (must sum to 100) ---
    bobot_data = [
        ("Target", 20, "Pencapaian target jumlah penagihan"),
        ("Realisasi", 20, "Pencapaian realisasi nominal penagihan"),
        ("Penagihan Berhasil", 15, "Persentase penugasan yang berhasil diselesaikan"),
        ("Verval", 15, "Persentase penugasan yang telah diverifikasi & divalidasi"),
        ("GPS Valid", 10, "Persentase pelaksanaan dengan lokasi GPS valid"),
        ("Ketepatan Waktu", 10, "Persentase penyelesaian tugas tepat waktu sebelum deadline"),
        ("Kelengkapan Dokumen", 10, "Persentase pelaksanaan dengan bukti dokumen lengkap"),
    ]
    for nama, bobot, ket in bobot_data:
        db.session.add(BobotKPI(nama_indikator=nama, bobot=bobot, keterangan=ket))

    # --- Target Periode ---
    periode_data = [
        ("2024-01", 2024, 1, 30, 500_000_000),
        ("2024-02", 2024, 2, 30, 500_000_000),
    ]
    periode_list = []
    for periode, tahun, bulan, tj, tn in periode_data:
        tp = TargetPeriode(
            periode=periode, tahun=tahun, bulan=bulan, target_jumlah=tj, target_nominal=tn
        )
        db.session.add(tp)
        periode_list.append(tp)
    db.session.flush()

    # --- Setting Insentif ---
    for periode, _, _, _, _ in periode_data:
        db.session.add(
            SettingInsentif(periode=periode, dana_mingguan=5_000_000, dana_bulanan=20_000_000)
        )
    db.session.flush()

    # --- Penugasan (beberapa tahap) ---
    periode_jan = periode_list[0]

    # 1) Penugasan completed + verified + approved (untuk pegawai 0)
    p1 = Penugasan(
        nomor_penugasan="PNG-2024-0001",
        pegawai_id=pegawai_list[0].id,
        wajib_pajak_id=wp_list[0].id,
        jenis_pekerjaan_id=jp_list[0].id,
        periode_id=periode_jan.id,
        target_jumlah=10,
        target_nominal=100_000_000,
        deadline=datetime(2024, 1, 25, 17, 0),
        status="active",
        catatan="Penagihan tunggakan tahun pajak 2023",
    )
    db.session.add(p1)
    db.session.flush()

    pl1 = PelaksanaanLapangan(
        penugasan_id=p1.id,
        tanggal_pelaksanaan=datetime(2024, 1, 20, 9, 0),
        latitude=-6.2088,
        longitude=106.8456,
        jarak_gps=15,
        gps_valid=True,
        foto_url=None,
        hasil_keterangan="Wajib pajak telah melunasi sebagian tunggakan.",
        realisasi_jumlah=8,
        realisasi_nominal=80_000_000,
        bukti_url=None,
        status="submitted",
        submitted_at=datetime(2024, 1, 20, 15, 0),
    )
    db.session.add(pl1)
    db.session.flush()

    v1 = Verifikasi(
        pelaksanaan_id=pl1.id,
        admin_id=admin.id,
        tanggal_verifikasi=datetime(2024, 1, 21, 10, 0),
        status="approved",
        catatan_verifikasi="Dokumen lengkap dan sesuai.",
    )
    db.session.add(v1)
    p1.status = "completed"

    # 2) Penugasan aktif, sudah ada pelaksanaan tapi belum diverifikasi (pegawai 1)
    p2 = Penugasan(
        nomor_penugasan="PNG-2024-0002",
        pegawai_id=pegawai_list[1].id,
        wajib_pajak_id=wp_list[1].id,
        jenis_pekerjaan_id=jp_list[1].id,
        periode_id=periode_jan.id,
        target_jumlah=5,
        target_nominal=50_000_000,
        deadline=datetime(2024, 1, 28, 17, 0),
        status="active",
        catatan="Verifikasi data wajib pajak baru",
    )
    db.session.add(p2)
    db.session.flush()

    pl2 = PelaksanaanLapangan(
        penugasan_id=p2.id,
        tanggal_pelaksanaan=datetime(2024, 1, 22, 10, 0),
        latitude=-6.2297,
        longitude=106.8175,
        jarak_gps=8,
        gps_valid=True,
        foto_url=None,
        hasil_keterangan="Verifikasi data alamat dan kontak wajib pajak selesai.",
        realisasi_jumlah=3,
        realisasi_nominal=25_000_000,
        bukti_url=None,
        status="submitted",
        submitted_at=datetime(2024, 1, 22, 14, 0),
    )
    db.session.add(pl2)

    # 3) Penugasan masih aktif, belum ada pelaksanaan (pegawai 2)
    p3 = Penugasan(
        nomor_penugasan="PNG-2024-0003",
        pegawai_id=pegawai_list[2].id,
        wajib_pajak_id=wp_list[2].id,
        jenis_pekerjaan_id=jp_list[2].id,
        periode_id=periode_jan.id,
        target_jumlah=3,
        target_nominal=30_000_000,
        deadline=datetime(2024, 2, 5, 17, 0),
        status="active",
        catatan="Sosialisasi kepatuhan pajak wilayah Bandung",
    )
    db.session.add(p3)

    # 4) Penugasan draft (belum dimulai)
    p4 = Penugasan(
        nomor_penugasan="PNG-2024-0004",
        pegawai_id=pegawai_list[0].id,
        wajib_pajak_id=wp_list[3].id,
        jenis_pekerjaan_id=jp_list[0].id,
        periode_id=periode_jan.id,
        target_jumlah=4,
        target_nominal=40_000_000,
        deadline=datetime(2024, 2, 10, 17, 0),
        status="draft",
        catatan="Menunggu jadwal pelaksanaan",
    )
    db.session.add(p4)

    db.session.commit()
    print("Seeding selesai.")
    print("Login sebagai admin -> username: admin, password: admin123")
    print("Login sebagai petugas -> username: petugas1/2/3, password: petugas123")


def seed():
    """Standalone entry point: creates its own app instance and seeds data."""
    app = create_app()
    with app.app_context():
        seed_data()


if __name__ == "__main__":
    seed()
