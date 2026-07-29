"""KPI calculation and incentive distribution service functions."""

from datetime import datetime

from app.extensions import db
from app.models import (
    BobotKPI,
    Insentif,
    KPIPegawai,
    PelaksanaanLapangan,
    Penugasan,
    SettingInsentif,
    Verifikasi,
)

DEFAULT_INDICATOR_KEYS = [
    "Target",
    "Realisasi",
    "Penagihan Berhasil",
    "Verval",
    "GPS Valid",
    "Ketepatan Waktu",
    "Kelengkapan Dokumen",
]


def _safe_ratio(numerator, denominator):
    if not denominator:
        return 0.0
    return (numerator / denominator) * 100.0


def get_bobot_map():
    """Return dict mapping indicator name -> bobot (weight), defaulting evenly if missing."""
    bobot_list = BobotKPI.query.all()
    mapping = {b.nama_indikator: b.bobot for b in bobot_list}
    if not mapping:
        default_weight = 100.0 / len(DEFAULT_INDICATOR_KEYS)
        mapping = {key: default_weight for key in DEFAULT_INDICATOR_KEYS}
    else:
        for key in DEFAULT_INDICATOR_KEYS:
            mapping.setdefault(key, 0.0)
    return mapping


def hitung_kpi_pegawai(pegawai_id, periode_id):
    """Calculate and persist the KPI for one employee in one period.

    Returns the KPIPegawai record (created or updated).
    """
    penugasan_list = Penugasan.query.filter_by(
        pegawai_id=pegawai_id, periode_id=periode_id
    ).all()

    total_penugasan = len(penugasan_list)
    penugasan_ids = [p.id for p in penugasan_list]

    pelaksanaan_list = []
    if penugasan_ids:
        pelaksanaan_list = PelaksanaanLapangan.query.filter(
            PelaksanaanLapangan.penugasan_id.in_(penugasan_ids)
        ).all()
    total_pelaksanaan = len(pelaksanaan_list)

    sum_target_jumlah = sum(p.target_jumlah or 0 for p in penugasan_list)
    sum_target_nominal = sum(p.target_nominal or 0 for p in penugasan_list)
    sum_realisasi_jumlah = sum(pl.realisasi_jumlah or 0 for pl in pelaksanaan_list)
    sum_realisasi_nominal = sum(pl.realisasi_nominal or 0 for pl in pelaksanaan_list)

    # 1. Target achievement (jumlah)
    nilai_target = _safe_ratio(sum_realisasi_jumlah, sum_target_jumlah)
    # 2. Realisasi nominal
    nilai_realisasi = _safe_ratio(sum_realisasi_nominal, sum_target_nominal)

    # 3. Penagihan Berhasil: penugasan yang berstatus completed
    penagihan_berhasil_count = sum(1 for p in penugasan_list if p.status == "completed")
    nilai_penagihan = _safe_ratio(penagihan_berhasil_count, total_penugasan)

    # 4. Verval: penugasan yang pelaksanaannya sudah diverifikasi approved
    verified_penugasan_ids = set()
    for pl in pelaksanaan_list:
        if pl.verifikasi and pl.verifikasi.status == "approved":
            verified_penugasan_ids.add(pl.penugasan_id)
    nilai_verval = _safe_ratio(len(verified_penugasan_ids), total_penugasan)

    # 5. GPS Valid
    gps_valid_count = sum(1 for pl in pelaksanaan_list if pl.gps_valid)
    nilai_gps = _safe_ratio(gps_valid_count, total_pelaksanaan)

    # 6. Ketepatan Waktu: submitted_at <= deadline penugasan
    on_time_count = 0
    penugasan_map = {p.id: p for p in penugasan_list}
    for pl in pelaksanaan_list:
        penugasan = penugasan_map.get(pl.penugasan_id)
        if penugasan and pl.submitted_at and pl.submitted_at <= penugasan.deadline:
            on_time_count += 1
    nilai_ketepatan = _safe_ratio(on_time_count, total_penugasan)

    # 7. Kelengkapan Dokumen: pelaksanaan dengan bukti_url terisi
    has_bukti_count = sum(1 for pl in pelaksanaan_list if pl.bukti_url)
    nilai_dokumen = _safe_ratio(has_bukti_count, total_pelaksanaan)

    bobot_map = get_bobot_map()
    nilai_kpi_total = (
        nilai_target * bobot_map.get("Target", 0) / 100.0
        + nilai_realisasi * bobot_map.get("Realisasi", 0) / 100.0
        + nilai_penagihan * bobot_map.get("Penagihan Berhasil", 0) / 100.0
        + nilai_verval * bobot_map.get("Verval", 0) / 100.0
        + nilai_gps * bobot_map.get("GPS Valid", 0) / 100.0
        + nilai_ketepatan * bobot_map.get("Ketepatan Waktu", 0) / 100.0
        + nilai_dokumen * bobot_map.get("Kelengkapan Dokumen", 0) / 100.0
    )

    kpi = KPIPegawai.query.filter_by(pegawai_id=pegawai_id, periode_id=periode_id).first()
    if kpi is None:
        kpi = KPIPegawai(pegawai_id=pegawai_id, periode_id=periode_id)
        db.session.add(kpi)

    kpi.nilai_target = round(nilai_target, 2)
    kpi.nilai_realisasi = round(nilai_realisasi, 2)
    kpi.nilai_penagihan = round(nilai_penagihan, 2)
    kpi.nilai_verval = round(nilai_verval, 2)
    kpi.nilai_gps = round(nilai_gps, 2)
    kpi.nilai_ketepatan = round(nilai_ketepatan, 2)
    kpi.nilai_dokumen = round(nilai_dokumen, 2)
    kpi.nilai_kpi_total = round(nilai_kpi_total, 2)
    kpi.calculated_at = datetime.utcnow()

    db.session.commit()
    return kpi


def hitung_kpi_untuk_periode(periode_id):
    """Recalculate KPI for every employee that has an assignment in the given period."""
    from app.models import Pegawai

    pegawai_ids = {
        row[0]
        for row in db.session.query(Penugasan.pegawai_id)
        .filter_by(periode_id=periode_id)
        .distinct()
        .all()
    }
    results = []
    for pegawai_id in pegawai_ids:
        results.append(hitung_kpi_pegawai(pegawai_id, periode_id))
    return results


def hitung_insentif_untuk_periode(periode_id):
    """Calculate and persist incentive distribution for all employees in a period."""
    from app.models import TargetPeriode

    periode = TargetPeriode.query.get(periode_id)
    if periode is None:
        return []

    setting = SettingInsentif.query.filter_by(periode=periode.periode).first()
    dana_mingguan = setting.dana_mingguan if setting else 0
    dana_bulanan = setting.dana_bulanan if setting else 0

    kpi_list = KPIPegawai.query.filter_by(periode_id=periode_id).all()
    total_kpi = sum(k.nilai_kpi_total for k in kpi_list)

    results = []
    for kpi in kpi_list:
        proporsi = _safe_ratio(kpi.nilai_kpi_total, total_kpi) if total_kpi else 0
        insentif = Insentif.query.filter_by(
            pegawai_id=kpi.pegawai_id, periode_id=periode_id
        ).first()
        if insentif is None:
            insentif = Insentif(pegawai_id=kpi.pegawai_id, periode_id=periode_id)
            db.session.add(insentif)

        insentif.nilai_kpi = kpi.nilai_kpi_total
        insentif.proporsi = round(proporsi, 2)
        insentif.insentif_mingguan = round(dana_mingguan * proporsi / 100.0, 2)
        insentif.insentif_bulanan = round(dana_bulanan * proporsi / 100.0, 2)
        insentif.calculated_at = datetime.utcnow()
        results.append(insentif)

    db.session.commit()
    return results
