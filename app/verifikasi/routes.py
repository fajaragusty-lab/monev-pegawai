from datetime import datetime

from flask import Blueprint, flash, redirect, render_template, request, url_for
from flask_login import current_user, login_required

from app.decorators import admin_required
from app.extensions import db
from app.kpi.services import hitung_kpi_pegawai
from app.models import PelaksanaanLapangan, Penugasan, Verifikasi

verifikasi_bp = Blueprint("verifikasi", __name__)


@verifikasi_bp.before_request
@login_required
@admin_required
def before_request():
    pass


@verifikasi_bp.route("/")
def index():
    status = request.args.get("status", "submitted")
    query = PelaksanaanLapangan.query
    if status:
        query = query.filter_by(status=status)
    items = query.order_by(PelaksanaanLapangan.submitted_at.desc()).all()
    return render_template("verifikasi/list.html", items=items, current_status=status)


@verifikasi_bp.route("/<int:pelaksanaan_id>")
def detail(pelaksanaan_id):
    item = PelaksanaanLapangan.query.get_or_404(pelaksanaan_id)
    return render_template("verifikasi/detail.html", item=item)


@verifikasi_bp.route("/<int:pelaksanaan_id>/proses", methods=["POST"])
def proses(pelaksanaan_id):
    pelaksanaan = PelaksanaanLapangan.query.get_or_404(pelaksanaan_id)
    keputusan = request.form.get("keputusan")  # approved | rejected
    catatan = request.form.get("catatan_verifikasi", "").strip()

    verifikasi = pelaksanaan.verifikasi
    if verifikasi is None:
        verifikasi = Verifikasi(pelaksanaan_id=pelaksanaan.id)
        db.session.add(verifikasi)

    verifikasi.admin_id = current_user.id
    verifikasi.tanggal_verifikasi = datetime.utcnow()
    verifikasi.status = keputusan
    verifikasi.catatan_verifikasi = catatan

    penugasan = pelaksanaan.penugasan

    if keputusan == "approved":
        penugasan.status = "completed"
        db.session.commit()
        # Trigger KPI calculation for the employee/period of this assignment.
        hitung_kpi_pegawai(penugasan.pegawai_id, penugasan.periode_id)
        flash("Pelaksanaan disetujui dan KPI berhasil dihitung ulang.", "success")
    else:
        # Rejected: keep the assignment active so the officer can redo the work.
        penugasan.status = "active"
        db.session.commit()
        flash("Pelaksanaan ditolak. Petugas perlu memperbaiki laporan.", "warning")

    return redirect(url_for("verifikasi.index"))
