from datetime import datetime

from flask import Blueprint, flash, redirect, render_template, request, url_for
from flask_login import login_required

from app.decorators import admin_required
from app.extensions import db
from app.models import JenisPekerjaan, Pegawai, Penugasan, TargetPeriode, WajibPajak

penugasan_bp = Blueprint("penugasan", __name__)


@penugasan_bp.before_request
@login_required
@admin_required
def before_request():
    pass


def _generate_nomor_penugasan():
    """Generate an auto incrementing assignment number like PNG-2024-0001."""
    year = datetime.utcnow().year
    count = Penugasan.query.filter(
        Penugasan.nomor_penugasan.like(f"PNG-{year}-%")
    ).count()
    return f"PNG-{year}-{count + 1:04d}"


@penugasan_bp.route("/")
def index():
    status = request.args.get("status", "")
    pegawai_id = request.args.get("pegawai_id", "")

    query = Penugasan.query
    if status:
        query = query.filter_by(status=status)
    if pegawai_id:
        query = query.filter_by(pegawai_id=pegawai_id)

    items = query.order_by(Penugasan.created_at.desc()).all()
    pegawai_list = Pegawai.query.order_by(Pegawai.nama).all()
    return render_template(
        "penugasan/list.html",
        items=items,
        pegawai_list=pegawai_list,
        current_status=status,
        current_pegawai=pegawai_id,
    )


@penugasan_bp.route("/create", methods=["GET", "POST"])
def create():
    pegawai_list = Pegawai.query.filter_by(aktif=True).order_by(Pegawai.nama).all()
    wajib_pajak_list = WajibPajak.query.order_by(WajibPajak.nama).all()
    jenis_pekerjaan_list = JenisPekerjaan.query.order_by(JenisPekerjaan.nama).all()
    periode_list = TargetPeriode.query.order_by(TargetPeriode.periode.desc()).all()

    if request.method == "POST":
        item = Penugasan(
            nomor_penugasan=_generate_nomor_penugasan(),
            pegawai_id=request.form["pegawai_id"],
            wajib_pajak_id=request.form["wajib_pajak_id"],
            jenis_pekerjaan_id=request.form["jenis_pekerjaan_id"],
            periode_id=request.form["periode_id"],
            target_jumlah=int(request.form.get("target_jumlah") or 1),
            target_nominal=float(request.form.get("target_nominal") or 0),
            deadline=datetime.strptime(request.form["deadline"], "%Y-%m-%dT%H:%M"),
            status=request.form.get("status", "draft"),
            catatan=request.form.get("catatan", "").strip(),
        )
        db.session.add(item)
        db.session.commit()
        flash(f"Penugasan {item.nomor_penugasan} berhasil dibuat.", "success")
        return redirect(url_for("penugasan.index"))

    return render_template(
        "penugasan/form.html",
        item=None,
        pegawai_list=pegawai_list,
        wajib_pajak_list=wajib_pajak_list,
        jenis_pekerjaan_list=jenis_pekerjaan_list,
        periode_list=periode_list,
    )


@penugasan_bp.route("/<int:item_id>")
def detail(item_id):
    item = Penugasan.query.get_or_404(item_id)
    return render_template("penugasan/detail.html", item=item)


@penugasan_bp.route("/<int:item_id>/edit", methods=["GET", "POST"])
def edit(item_id):
    item = Penugasan.query.get_or_404(item_id)
    pegawai_list = Pegawai.query.filter_by(aktif=True).order_by(Pegawai.nama).all()
    wajib_pajak_list = WajibPajak.query.order_by(WajibPajak.nama).all()
    jenis_pekerjaan_list = JenisPekerjaan.query.order_by(JenisPekerjaan.nama).all()
    periode_list = TargetPeriode.query.order_by(TargetPeriode.periode.desc()).all()

    if request.method == "POST":
        item.pegawai_id = request.form["pegawai_id"]
        item.wajib_pajak_id = request.form["wajib_pajak_id"]
        item.jenis_pekerjaan_id = request.form["jenis_pekerjaan_id"]
        item.periode_id = request.form["periode_id"]
        item.target_jumlah = int(request.form.get("target_jumlah") or 1)
        item.target_nominal = float(request.form.get("target_nominal") or 0)
        item.deadline = datetime.strptime(request.form["deadline"], "%Y-%m-%dT%H:%M")
        item.status = request.form.get("status", "draft")
        item.catatan = request.form.get("catatan", "").strip()
        db.session.commit()
        flash("Penugasan berhasil diperbarui.", "success")
        return redirect(url_for("penugasan.detail", item_id=item.id))

    return render_template(
        "penugasan/form.html",
        item=item,
        pegawai_list=pegawai_list,
        wajib_pajak_list=wajib_pajak_list,
        jenis_pekerjaan_list=jenis_pekerjaan_list,
        periode_list=periode_list,
    )


@penugasan_bp.route("/<int:item_id>/delete", methods=["POST"])
def delete(item_id):
    item = Penugasan.query.get_or_404(item_id)
    db.session.delete(item)
    db.session.commit()
    flash("Penugasan berhasil dihapus.", "success")
    return redirect(url_for("penugasan.index"))
