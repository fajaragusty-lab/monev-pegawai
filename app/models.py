from datetime import datetime

from flask_login import UserMixin
from werkzeug.security import check_password_hash, generate_password_hash

from app.extensions import db


class User(UserMixin, db.Model):
    """Application user account (admin or petugas)."""

    __tablename__ = "users"

    id = db.Column(db.Integer, primary_key=True)
    username = db.Column(db.String(64), unique=True, nullable=False)
    password_hash = db.Column(db.String(256), nullable=False)
    role = db.Column(db.String(20), nullable=False, default="petugas")  # admin | petugas
    nama_lengkap = db.Column(db.String(120), nullable=False)
    created_at = db.Column(db.DateTime, default=datetime.utcnow)

    pegawai = db.relationship("Pegawai", backref="user", uselist=False)

    def set_password(self, password):
        self.password_hash = generate_password_hash(password)

    def check_password(self, password):
        return check_password_hash(self.password_hash, password)

    def is_admin(self):
        return self.role == "admin"

    def __repr__(self):
        return f"<User {self.username} ({self.role})>"


class Wilayah(db.Model):
    """Territory / region master data."""

    __tablename__ = "wilayah"

    id = db.Column(db.Integer, primary_key=True)
    nama_wilayah = db.Column(db.String(120), nullable=False)
    kode = db.Column(db.String(20), unique=True, nullable=False)

    pegawai_list = db.relationship("Pegawai", backref="wilayah", lazy=True)
    wajib_pajak_list = db.relationship("WajibPajak", backref="wilayah", lazy=True)

    def __repr__(self):
        return f"<Wilayah {self.kode} - {self.nama_wilayah}>"


class Pegawai(db.Model):
    """Employee (tax collection officer) master data."""

    __tablename__ = "pegawai"

    id = db.Column(db.Integer, primary_key=True)
    nama = db.Column(db.String(120), nullable=False)
    nip = db.Column(db.String(30), unique=True, nullable=False)
    jabatan = db.Column(db.String(80), nullable=False)
    wilayah_id = db.Column(db.Integer, db.ForeignKey("wilayah.id"), nullable=True)
    user_id = db.Column(db.Integer, db.ForeignKey("users.id"), nullable=True)
    aktif = db.Column(db.Boolean, default=True)

    penugasan_list = db.relationship("Penugasan", backref="pegawai", lazy=True)
    kpi_list = db.relationship("KPIPegawai", backref="pegawai", lazy=True)
    insentif_list = db.relationship("Insentif", backref="pegawai", lazy=True)

    def __repr__(self):
        return f"<Pegawai {self.nip} - {self.nama}>"


class WajibPajak(db.Model):
    """Taxpayer master data."""

    __tablename__ = "wajib_pajak"

    id = db.Column(db.Integer, primary_key=True)
    nama = db.Column(db.String(150), nullable=False)
    npwp = db.Column(db.String(30), unique=True, nullable=False)
    alamat = db.Column(db.Text, nullable=True)
    wilayah_id = db.Column(db.Integer, db.ForeignKey("wilayah.id"), nullable=True)
    jenis_pajak = db.Column(db.String(80), nullable=True)

    penugasan_list = db.relationship("Penugasan", backref="wajib_pajak", lazy=True)

    def __repr__(self):
        return f"<WajibPajak {self.npwp} - {self.nama}>"


class JenisPekerjaan(db.Model):
    """Job type master data."""

    __tablename__ = "jenis_pekerjaan"

    id = db.Column(db.Integer, primary_key=True)
    nama = db.Column(db.String(120), nullable=False)
    deskripsi = db.Column(db.Text, nullable=True)
    satuan = db.Column(db.String(30), nullable=True)

    penugasan_list = db.relationship("Penugasan", backref="jenis_pekerjaan", lazy=True)

    def __repr__(self):
        return f"<JenisPekerjaan {self.nama}>"


class BobotKPI(db.Model):
    """KPI weight master data. Weights should sum to 100."""

    __tablename__ = "bobot_kpi"

    id = db.Column(db.Integer, primary_key=True)
    nama_indikator = db.Column(db.String(120), nullable=False)
    bobot = db.Column(db.Float, nullable=False)  # percentage
    keterangan = db.Column(db.Text, nullable=True)

    def __repr__(self):
        return f"<BobotKPI {self.nama_indikator} ({self.bobot}%)>"


class SettingInsentif(db.Model):
    """Incentive fund settings per period."""

    __tablename__ = "setting_insentif"

    id = db.Column(db.Integer, primary_key=True)
    periode = db.Column(db.String(20), nullable=False, unique=True)  # e.g. "2024-01"
    dana_mingguan = db.Column(db.Float, default=0)
    dana_bulanan = db.Column(db.Float, default=0)

    def __repr__(self):
        return f"<SettingInsentif {self.periode}>"


class TargetPeriode(db.Model):
    """Target for a given period (month/year)."""

    __tablename__ = "target_periode"

    id = db.Column(db.Integer, primary_key=True)
    periode = db.Column(db.String(20), nullable=False, unique=True)  # e.g. "2024-01"
    tahun = db.Column(db.Integer, nullable=False)
    bulan = db.Column(db.Integer, nullable=False)
    target_jumlah = db.Column(db.Integer, default=0)
    target_nominal = db.Column(db.Float, default=0)

    penugasan_list = db.relationship("Penugasan", backref="periode", lazy=True)
    kpi_list = db.relationship("KPIPegawai", backref="periode", lazy=True)
    insentif_list = db.relationship("Insentif", backref="periode", lazy=True)

    def __repr__(self):
        return f"<TargetPeriode {self.periode}>"


class Penugasan(db.Model):
    """Assignment given by admin to an officer for a specific taxpayer."""

    __tablename__ = "penugasan"

    id = db.Column(db.Integer, primary_key=True)
    nomor_penugasan = db.Column(db.String(40), unique=True, nullable=False)
    pegawai_id = db.Column(db.Integer, db.ForeignKey("pegawai.id"), nullable=False)
    wajib_pajak_id = db.Column(db.Integer, db.ForeignKey("wajib_pajak.id"), nullable=False)
    jenis_pekerjaan_id = db.Column(db.Integer, db.ForeignKey("jenis_pekerjaan.id"), nullable=False)
    periode_id = db.Column(db.Integer, db.ForeignKey("target_periode.id"), nullable=False)
    target_jumlah = db.Column(db.Integer, default=1)
    target_nominal = db.Column(db.Float, default=0)
    deadline = db.Column(db.DateTime, nullable=False)
    status = db.Column(db.String(20), default="draft")  # draft/active/completed/cancelled
    catatan = db.Column(db.Text, nullable=True)
    created_at = db.Column(db.DateTime, default=datetime.utcnow)

    pelaksanaan_list = db.relationship(
        "PelaksanaanLapangan", backref="penugasan", lazy=True, cascade="all, delete-orphan"
    )

    def __repr__(self):
        return f"<Penugasan {self.nomor_penugasan}>"


class PelaksanaanLapangan(db.Model):
    """Field execution report submitted by officer for an assignment."""

    __tablename__ = "pelaksanaan_lapangan"

    id = db.Column(db.Integer, primary_key=True)
    penugasan_id = db.Column(db.Integer, db.ForeignKey("penugasan.id"), nullable=False)
    tanggal_pelaksanaan = db.Column(db.DateTime, default=datetime.utcnow)
    latitude = db.Column(db.Float, nullable=True)
    longitude = db.Column(db.Float, nullable=True)
    jarak_gps = db.Column(db.Float, nullable=True)  # meters from target, simplified
    gps_valid = db.Column(db.Boolean, default=False)
    foto_url = db.Column(db.String(255), nullable=True)
    hasil_keterangan = db.Column(db.Text, nullable=True)
    realisasi_jumlah = db.Column(db.Integer, default=0)
    realisasi_nominal = db.Column(db.Float, default=0)
    bukti_url = db.Column(db.String(255), nullable=True)
    status = db.Column(db.String(20), default="draft")  # draft/submitted
    submitted_at = db.Column(db.DateTime, nullable=True)

    verifikasi = db.relationship(
        "Verifikasi", backref="pelaksanaan", uselist=False, cascade="all, delete-orphan"
    )

    def __repr__(self):
        return f"<PelaksanaanLapangan {self.id} penugasan={self.penugasan_id}>"


class Verifikasi(db.Model):
    """Verification of a field execution by admin."""

    __tablename__ = "verifikasi"

    id = db.Column(db.Integer, primary_key=True)
    pelaksanaan_id = db.Column(db.Integer, db.ForeignKey("pelaksanaan_lapangan.id"), nullable=False)
    admin_id = db.Column(db.Integer, db.ForeignKey("users.id"), nullable=True)
    tanggal_verifikasi = db.Column(db.DateTime, default=datetime.utcnow)
    status = db.Column(db.String(20), default="pending")  # pending/approved/rejected
    catatan_verifikasi = db.Column(db.Text, nullable=True)

    admin = db.relationship("User", foreign_keys=[admin_id])

    def __repr__(self):
        return f"<Verifikasi {self.id} status={self.status}>"


class KPIPegawai(db.Model):
    """Calculated KPI values for an employee in a given period."""

    __tablename__ = "kpi_pegawai"

    id = db.Column(db.Integer, primary_key=True)
    pegawai_id = db.Column(db.Integer, db.ForeignKey("pegawai.id"), nullable=False)
    periode_id = db.Column(db.Integer, db.ForeignKey("target_periode.id"), nullable=False)
    nilai_target = db.Column(db.Float, default=0)
    nilai_realisasi = db.Column(db.Float, default=0)
    nilai_penagihan = db.Column(db.Float, default=0)
    nilai_verval = db.Column(db.Float, default=0)
    nilai_gps = db.Column(db.Float, default=0)
    nilai_ketepatan = db.Column(db.Float, default=0)
    nilai_dokumen = db.Column(db.Float, default=0)
    nilai_kpi_total = db.Column(db.Float, default=0)
    calculated_at = db.Column(db.DateTime, default=datetime.utcnow)

    __table_args__ = (db.UniqueConstraint("pegawai_id", "periode_id", name="uq_kpi_pegawai_periode"),)

    def __repr__(self):
        return f"<KPIPegawai pegawai={self.pegawai_id} periode={self.periode_id} total={self.nilai_kpi_total}>"


class Insentif(db.Model):
    """Calculated incentive for an employee in a given period."""

    __tablename__ = "insentif"

    id = db.Column(db.Integer, primary_key=True)
    pegawai_id = db.Column(db.Integer, db.ForeignKey("pegawai.id"), nullable=False)
    periode_id = db.Column(db.Integer, db.ForeignKey("target_periode.id"), nullable=False)
    nilai_kpi = db.Column(db.Float, default=0)
    proporsi = db.Column(db.Float, default=0)  # percentage share
    insentif_mingguan = db.Column(db.Float, default=0)
    insentif_bulanan = db.Column(db.Float, default=0)
    calculated_at = db.Column(db.DateTime, default=datetime.utcnow)

    __table_args__ = (db.UniqueConstraint("pegawai_id", "periode_id", name="uq_insentif_pegawai_periode"),)

    def __repr__(self):
        return f"<Insentif pegawai={self.pegawai_id} periode={self.periode_id}>"
