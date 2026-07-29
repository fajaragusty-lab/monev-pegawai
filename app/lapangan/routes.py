import os
import uuid
from datetime import datetime

from flask import (
    Blueprint,
    current_app,
    flash,
    redirect,
    render_template,
    request,
    url_for,
)
from flask_login import current_user, login_required
from werkzeug.utils import secure_filename

from app.decorators import petugas_required
from app.extensions import db
from app.models import PelaksanaanLapangan, Penugasan

lapangan_bp = Blueprint("lapangan", __name__)


@lapangan_bp.before_request
@login_required
@petugas_required
def before_request():
    pass


def _allowed_file(filename):
    return (
        "." in filename
        and filename.rsplit(".", 1)[1].lower() in current_app.config["ALLOWED_EXTENSIONS"]
    )


def _save_upload(file_storage):
    if not file_storage or file_storage.filename == "":
        return None
    if not _allowed_file(file_storage.filename):
        return None
    ext = file_storage.filename.rsplit(".", 1)[1].lower()
    filename = secure_filename(f"{uuid.uuid4().hex}.{ext}")
    file_storage.save(os.path.join(current_app.config["UPLOAD_FOLDER"], filename))
    return filename


def _get_my_pegawai():
    return current_user.pegawai


@lapangan_bp.route("/")
def index():
    pegawai = _get_my_pegawai()
    if not pegawai:
        flash("Akun anda belum terhubung dengan data pegawai. Hubungi admin.", "warning")
        return render_template("lapangan/list.html", items=[])

    items = (
        Penugasan.query.filter_by(pegawai_id=pegawai.id)
        .filter(Penugasan.status.in_(["active", "completed"]))
        .order_by(Penugasan.deadline)
        .all()
    )
    return render_template("lapangan/list.html", items=items)


@lapangan_bp.route("/<int:penugasan_id>")
def detail(penugasan_id):
    pegawai = _get_my_pegawai()
    item = Penugasan.query.get_or_404(penugasan_id)
    if pegawai is None or item.pegawai_id != pegawai.id:
        flash("Anda tidak memiliki akses ke penugasan ini.", "danger")
        return redirect(url_for("lapangan.index"))
    return render_template("lapangan/detail.html", item=item)


@lapangan_bp.route("/<int:penugasan_id>/laksanakan", methods=["GET", "POST"])
def laksanakan(penugasan_id):
    pegawai = _get_my_pegawai()
    penugasan = Penugasan.query.get_or_404(penugasan_id)
    if pegawai is None or penugasan.pegawai_id != pegawai.id:
        flash("Anda tidak memiliki akses ke penugasan ini.", "danger")
        return redirect(url_for("lapangan.index"))

    if penugasan.status != "active":
        flash("Penugasan ini tidak dalam status aktif.", "warning")
        return redirect(url_for("lapangan.detail", penugasan_id=penugasan.id))

    if request.method == "POST":
        latitude = request.form.get("latitude")
        longitude = request.form.get("longitude")
        # Simplified GPS validation: valid as long as both coordinates provided.
        gps_valid = bool(latitude) and bool(longitude)

        foto_filename = _save_upload(request.files.get("foto"))
        bukti_filename = _save_upload(request.files.get("bukti"))

        pelaksanaan = PelaksanaanLapangan(
            penugasan_id=penugasan.id,
            tanggal_pelaksanaan=datetime.utcnow(),
            latitude=float(latitude) if latitude else None,
            longitude=float(longitude) if longitude else None,
            jarak_gps=float(request.form.get("jarak_gps") or 0),
            gps_valid=gps_valid,
            foto_url=foto_filename,
            hasil_keterangan=request.form.get("hasil_keterangan", "").strip(),
            realisasi_jumlah=int(request.form.get("realisasi_jumlah") or 0),
            realisasi_nominal=float(request.form.get("realisasi_nominal") or 0),
            bukti_url=bukti_filename,
            status="draft",
        )

        action = request.form.get("action", "draft")
        if action == "submit":
            pelaksanaan.status = "submitted"
            pelaksanaan.submitted_at = datetime.utcnow()

        db.session.add(pelaksanaan)
        db.session.commit()

        if action == "submit":
            flash("Laporan pelaksanaan berhasil disubmit untuk verifikasi.", "success")
        else:
            flash("Laporan pelaksanaan berhasil disimpan sebagai draft.", "info")
        return redirect(url_for("lapangan.detail", penugasan_id=penugasan.id))

    return render_template("lapangan/form.html", item=penugasan)


@lapangan_bp.route("/pelaksanaan/<int:pelaksanaan_id>/submit", methods=["POST"])
def submit(pelaksanaan_id):
    pelaksanaan = PelaksanaanLapangan.query.get_or_404(pelaksanaan_id)
    pegawai = _get_my_pegawai()
    if pegawai is None or pelaksanaan.penugasan.pegawai_id != pegawai.id:
        flash("Anda tidak memiliki akses.", "danger")
        return redirect(url_for("lapangan.index"))

    pelaksanaan.status = "submitted"
    pelaksanaan.submitted_at = datetime.utcnow()
    db.session.commit()
    flash("Laporan berhasil disubmit untuk verifikasi.", "success")
    return redirect(url_for("lapangan.detail", penugasan_id=pelaksanaan.penugasan_id))
