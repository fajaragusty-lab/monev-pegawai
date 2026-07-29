from datetime import datetime

from flask import Blueprint, flash, redirect, render_template, request, url_for
from flask_login import login_required
from werkzeug.security import generate_password_hash

from app.decorators import admin_required
from app.extensions import db
from app.models import (
    BobotKPI,
    JenisPekerjaan,
    Pegawai,
    SettingInsentif,
    TargetPeriode,
    User,
    WajibPajak,
    Wilayah,
)

master_bp = Blueprint("master", __name__)


@master_bp.before_request
@login_required
@admin_required
def before_request():
    """All master data routes require admin login."""
    pass


@master_bp.route("/")
def index():
    return render_template("master/index.html")


# ---------------------------------------------------------------------------
# Wilayah
# ---------------------------------------------------------------------------
@master_bp.route("/wilayah")
def wilayah_list():
    items = Wilayah.query.order_by(Wilayah.nama_wilayah).all()
    return render_template("master/wilayah/list.html", items=items)


@master_bp.route("/wilayah/create", methods=["GET", "POST"])
def wilayah_create():
    if request.method == "POST":
        item = Wilayah(
            nama_wilayah=request.form["nama_wilayah"].strip(),
            kode=request.form["kode"].strip(),
        )
        db.session.add(item)
        db.session.commit()
        flash("Wilayah berhasil ditambahkan.", "success")
        return redirect(url_for("master.wilayah_list"))
    return render_template("master/wilayah/form.html", item=None)


@master_bp.route("/wilayah/<int:item_id>/edit", methods=["GET", "POST"])
def wilayah_edit(item_id):
    item = Wilayah.query.get_or_404(item_id)
    if request.method == "POST":
        item.nama_wilayah = request.form["nama_wilayah"].strip()
        item.kode = request.form["kode"].strip()
        db.session.commit()
        flash("Wilayah berhasil diperbarui.", "success")
        return redirect(url_for("master.wilayah_list"))
    return render_template("master/wilayah/form.html", item=item)


@master_bp.route("/wilayah/<int:item_id>/delete", methods=["POST"])
def wilayah_delete(item_id):
    item = Wilayah.query.get_or_404(item_id)
    db.session.delete(item)
    db.session.commit()
    flash("Wilayah berhasil dihapus.", "success")
    return redirect(url_for("master.wilayah_list"))


# ---------------------------------------------------------------------------
# Pegawai
# ---------------------------------------------------------------------------
@master_bp.route("/pegawai")
def pegawai_list():
    items = Pegawai.query.order_by(Pegawai.nama).all()
    return render_template("master/pegawai/list.html", items=items)


@master_bp.route("/pegawai/create", methods=["GET", "POST"])
def pegawai_create():
    wilayah_list = Wilayah.query.order_by(Wilayah.nama_wilayah).all()
    if request.method == "POST":
        user_id = None
        create_account = request.form.get("create_account") == "on"
        if create_account:
            username = request.form.get("username", "").strip()
            if username:
                if User.query.filter_by(username=username).first():
                    flash("Username sudah digunakan.", "danger")
                    return render_template(
                        "master/pegawai/form.html", item=None, wilayah_list=wilayah_list
                    )
                user = User(
                    username=username,
                    nama_lengkap=request.form["nama"].strip(),
                    role="petugas",
                )
                user.set_password(request.form.get("password") or "petugas123")
                db.session.add(user)
                db.session.flush()
                user_id = user.id

        item = Pegawai(
            nama=request.form["nama"].strip(),
            nip=request.form["nip"].strip(),
            jabatan=request.form["jabatan"].strip(),
            wilayah_id=request.form.get("wilayah_id") or None,
            user_id=user_id,
            aktif=request.form.get("aktif") == "on",
        )
        db.session.add(item)
        db.session.commit()
        flash("Pegawai berhasil ditambahkan.", "success")
        return redirect(url_for("master.pegawai_list"))
    return render_template("master/pegawai/form.html", item=None, wilayah_list=wilayah_list)


@master_bp.route("/pegawai/<int:item_id>/edit", methods=["GET", "POST"])
def pegawai_edit(item_id):
    item = Pegawai.query.get_or_404(item_id)
    wilayah_list = Wilayah.query.order_by(Wilayah.nama_wilayah).all()
    if request.method == "POST":
        item.nama = request.form["nama"].strip()
        item.nip = request.form["nip"].strip()
        item.jabatan = request.form["jabatan"].strip()
        item.wilayah_id = request.form.get("wilayah_id") or None
        item.aktif = request.form.get("aktif") == "on"
        db.session.commit()
        flash("Pegawai berhasil diperbarui.", "success")
        return redirect(url_for("master.pegawai_list"))
    return render_template("master/pegawai/form.html", item=item, wilayah_list=wilayah_list)


@master_bp.route("/pegawai/<int:item_id>/delete", methods=["POST"])
def pegawai_delete(item_id):
    item = Pegawai.query.get_or_404(item_id)
    db.session.delete(item)
    db.session.commit()
    flash("Pegawai berhasil dihapus.", "success")
    return redirect(url_for("master.pegawai_list"))


# ---------------------------------------------------------------------------
# Wajib Pajak
# ---------------------------------------------------------------------------
@master_bp.route("/wajib-pajak")
def wajib_pajak_list():
    items = WajibPajak.query.order_by(WajibPajak.nama).all()
    return render_template("master/wajib_pajak/list.html", items=items)


@master_bp.route("/wajib-pajak/create", methods=["GET", "POST"])
def wajib_pajak_create():
    wilayah_list = Wilayah.query.order_by(Wilayah.nama_wilayah).all()
    if request.method == "POST":
        item = WajibPajak(
            nama=request.form["nama"].strip(),
            npwp=request.form["npwp"].strip(),
            alamat=request.form.get("alamat", "").strip(),
            wilayah_id=request.form.get("wilayah_id") or None,
            jenis_pajak=request.form.get("jenis_pajak", "").strip(),
        )
        db.session.add(item)
        db.session.commit()
        flash("Wajib Pajak berhasil ditambahkan.", "success")
        return redirect(url_for("master.wajib_pajak_list"))
    return render_template("master/wajib_pajak/form.html", item=None, wilayah_list=wilayah_list)


@master_bp.route("/wajib-pajak/<int:item_id>/edit", methods=["GET", "POST"])
def wajib_pajak_edit(item_id):
    item = WajibPajak.query.get_or_404(item_id)
    wilayah_list = Wilayah.query.order_by(Wilayah.nama_wilayah).all()
    if request.method == "POST":
        item.nama = request.form["nama"].strip()
        item.npwp = request.form["npwp"].strip()
        item.alamat = request.form.get("alamat", "").strip()
        item.wilayah_id = request.form.get("wilayah_id") or None
        item.jenis_pajak = request.form.get("jenis_pajak", "").strip()
        db.session.commit()
        flash("Wajib Pajak berhasil diperbarui.", "success")
        return redirect(url_for("master.wajib_pajak_list"))
    return render_template("master/wajib_pajak/form.html", item=item, wilayah_list=wilayah_list)


@master_bp.route("/wajib-pajak/<int:item_id>/delete", methods=["POST"])
def wajib_pajak_delete(item_id):
    item = WajibPajak.query.get_or_404(item_id)
    db.session.delete(item)
    db.session.commit()
    flash("Wajib Pajak berhasil dihapus.", "success")
    return redirect(url_for("master.wajib_pajak_list"))


# ---------------------------------------------------------------------------
# Jenis Pekerjaan
# ---------------------------------------------------------------------------
@master_bp.route("/jenis-pekerjaan")
def jenis_pekerjaan_list():
    items = JenisPekerjaan.query.order_by(JenisPekerjaan.nama).all()
    return render_template("master/jenis_pekerjaan/list.html", items=items)


@master_bp.route("/jenis-pekerjaan/create", methods=["GET", "POST"])
def jenis_pekerjaan_create():
    if request.method == "POST":
        item = JenisPekerjaan(
            nama=request.form["nama"].strip(),
            deskripsi=request.form.get("deskripsi", "").strip(),
            satuan=request.form.get("satuan", "").strip(),
        )
        db.session.add(item)
        db.session.commit()
        flash("Jenis Pekerjaan berhasil ditambahkan.", "success")
        return redirect(url_for("master.jenis_pekerjaan_list"))
    return render_template("master/jenis_pekerjaan/form.html", item=None)


@master_bp.route("/jenis-pekerjaan/<int:item_id>/edit", methods=["GET", "POST"])
def jenis_pekerjaan_edit(item_id):
    item = JenisPekerjaan.query.get_or_404(item_id)
    if request.method == "POST":
        item.nama = request.form["nama"].strip()
        item.deskripsi = request.form.get("deskripsi", "").strip()
        item.satuan = request.form.get("satuan", "").strip()
        db.session.commit()
        flash("Jenis Pekerjaan berhasil diperbarui.", "success")
        return redirect(url_for("master.jenis_pekerjaan_list"))
    return render_template("master/jenis_pekerjaan/form.html", item=item)


@master_bp.route("/jenis-pekerjaan/<int:item_id>/delete", methods=["POST"])
def jenis_pekerjaan_delete(item_id):
    item = JenisPekerjaan.query.get_or_404(item_id)
    db.session.delete(item)
    db.session.commit()
    flash("Jenis Pekerjaan berhasil dihapus.", "success")
    return redirect(url_for("master.jenis_pekerjaan_list"))


# ---------------------------------------------------------------------------
# Bobot KPI
# ---------------------------------------------------------------------------
@master_bp.route("/bobot-kpi")
def bobot_kpi_list():
    items = BobotKPI.query.order_by(BobotKPI.id).all()
    total_bobot = sum(item.bobot for item in items)
    return render_template("master/bobot_kpi/list.html", items=items, total_bobot=total_bobot)


@master_bp.route("/bobot-kpi/create", methods=["GET", "POST"])
def bobot_kpi_create():
    if request.method == "POST":
        item = BobotKPI(
            nama_indikator=request.form["nama_indikator"].strip(),
            bobot=float(request.form["bobot"]),
            keterangan=request.form.get("keterangan", "").strip(),
        )
        db.session.add(item)
        db.session.commit()
        flash("Bobot KPI berhasil ditambahkan.", "success")
        return redirect(url_for("master.bobot_kpi_list"))
    return render_template("master/bobot_kpi/form.html", item=None)


@master_bp.route("/bobot-kpi/<int:item_id>/edit", methods=["GET", "POST"])
def bobot_kpi_edit(item_id):
    item = BobotKPI.query.get_or_404(item_id)
    if request.method == "POST":
        item.nama_indikator = request.form["nama_indikator"].strip()
        item.bobot = float(request.form["bobot"])
        item.keterangan = request.form.get("keterangan", "").strip()
        db.session.commit()
        flash("Bobot KPI berhasil diperbarui.", "success")
        return redirect(url_for("master.bobot_kpi_list"))
    return render_template("master/bobot_kpi/form.html", item=item)


@master_bp.route("/bobot-kpi/<int:item_id>/delete", methods=["POST"])
def bobot_kpi_delete(item_id):
    item = BobotKPI.query.get_or_404(item_id)
    db.session.delete(item)
    db.session.commit()
    flash("Bobot KPI berhasil dihapus.", "success")
    return redirect(url_for("master.bobot_kpi_list"))


# ---------------------------------------------------------------------------
# Setting Insentif
# ---------------------------------------------------------------------------
@master_bp.route("/setting-insentif")
def setting_insentif_list():
    items = SettingInsentif.query.order_by(SettingInsentif.periode.desc()).all()
    return render_template("master/setting_insentif/list.html", items=items)


@master_bp.route("/setting-insentif/create", methods=["GET", "POST"])
def setting_insentif_create():
    if request.method == "POST":
        item = SettingInsentif(
            periode=request.form["periode"].strip(),
            dana_mingguan=float(request.form.get("dana_mingguan") or 0),
            dana_bulanan=float(request.form.get("dana_bulanan") or 0),
        )
        db.session.add(item)
        db.session.commit()
        flash("Setting Insentif berhasil ditambahkan.", "success")
        return redirect(url_for("master.setting_insentif_list"))
    return render_template("master/setting_insentif/form.html", item=None)


@master_bp.route("/setting-insentif/<int:item_id>/edit", methods=["GET", "POST"])
def setting_insentif_edit(item_id):
    item = SettingInsentif.query.get_or_404(item_id)
    if request.method == "POST":
        item.periode = request.form["periode"].strip()
        item.dana_mingguan = float(request.form.get("dana_mingguan") or 0)
        item.dana_bulanan = float(request.form.get("dana_bulanan") or 0)
        db.session.commit()
        flash("Setting Insentif berhasil diperbarui.", "success")
        return redirect(url_for("master.setting_insentif_list"))
    return render_template("master/setting_insentif/form.html", item=item)


@master_bp.route("/setting-insentif/<int:item_id>/delete", methods=["POST"])
def setting_insentif_delete(item_id):
    item = SettingInsentif.query.get_or_404(item_id)
    db.session.delete(item)
    db.session.commit()
    flash("Setting Insentif berhasil dihapus.", "success")
    return redirect(url_for("master.setting_insentif_list"))


# ---------------------------------------------------------------------------
# Target Periode
# ---------------------------------------------------------------------------
@master_bp.route("/target-periode")
def target_periode_list():
    items = TargetPeriode.query.order_by(TargetPeriode.periode.desc()).all()
    return render_template("master/target_periode/list.html", items=items)


@master_bp.route("/target-periode/create", methods=["GET", "POST"])
def target_periode_create():
    if request.method == "POST":
        item = TargetPeriode(
            periode=request.form["periode"].strip(),
            tahun=int(request.form["tahun"]),
            bulan=int(request.form["bulan"]),
            target_jumlah=int(request.form.get("target_jumlah") or 0),
            target_nominal=float(request.form.get("target_nominal") or 0),
        )
        db.session.add(item)
        db.session.commit()
        flash("Target Periode berhasil ditambahkan.", "success")
        return redirect(url_for("master.target_periode_list"))
    return render_template("master/target_periode/form.html", item=None)


@master_bp.route("/target-periode/<int:item_id>/edit", methods=["GET", "POST"])
def target_periode_edit(item_id):
    item = TargetPeriode.query.get_or_404(item_id)
    if request.method == "POST":
        item.periode = request.form["periode"].strip()
        item.tahun = int(request.form["tahun"])
        item.bulan = int(request.form["bulan"])
        item.target_jumlah = int(request.form.get("target_jumlah") or 0)
        item.target_nominal = float(request.form.get("target_nominal") or 0)
        db.session.commit()
        flash("Target Periode berhasil diperbarui.", "success")
        return redirect(url_for("master.target_periode_list"))
    return render_template("master/target_periode/form.html", item=item)


@master_bp.route("/target-periode/<int:item_id>/delete", methods=["POST"])
def target_periode_delete(item_id):
    item = TargetPeriode.query.get_or_404(item_id)
    db.session.delete(item)
    db.session.commit()
    flash("Target Periode berhasil dihapus.", "success")
    return redirect(url_for("master.target_periode_list"))
