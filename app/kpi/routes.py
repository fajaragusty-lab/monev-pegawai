from flask import Blueprint, flash, redirect, render_template, request, url_for
from flask_login import login_required

from app.decorators import admin_required
from app.kpi.services import hitung_insentif_untuk_periode, hitung_kpi_untuk_periode
from app.models import Insentif, KPIPegawai, TargetPeriode

kpi_bp = Blueprint("kpi", __name__)


@kpi_bp.before_request
@login_required
@admin_required
def before_request():
    pass


def _get_selected_periode():
    periode_id = request.args.get("periode_id", type=int)
    periode_list = TargetPeriode.query.order_by(TargetPeriode.periode.desc()).all()
    if periode_id is None and periode_list:
        periode_id = periode_list[0].id
    return periode_id, periode_list


@kpi_bp.route("/")
def index():
    periode_id, periode_list = _get_selected_periode()
    items = []
    if periode_id:
        items = (
            KPIPegawai.query.filter_by(periode_id=periode_id)
            .order_by(KPIPegawai.nilai_kpi_total.desc())
            .all()
        )
    return render_template(
        "kpi/index.html", items=items, periode_list=periode_list, periode_id=periode_id
    )


@kpi_bp.route("/hitung", methods=["POST"])
def hitung():
    periode_id = request.form.get("periode_id", type=int)
    if not periode_id:
        flash("Pilih periode terlebih dahulu.", "warning")
        return redirect(url_for("kpi.index"))

    results = hitung_kpi_untuk_periode(periode_id)
    flash(f"KPI berhasil dihitung untuk {len(results)} pegawai.", "success")
    return redirect(url_for("kpi.index", periode_id=periode_id))


@kpi_bp.route("/ranking")
def ranking():
    periode_id, periode_list = _get_selected_periode()
    items = []
    if periode_id:
        items = (
            KPIPegawai.query.filter_by(periode_id=periode_id)
            .order_by(KPIPegawai.nilai_kpi_total.desc())
            .all()
        )
    return render_template(
        "kpi/ranking.html", items=items, periode_list=periode_list, periode_id=periode_id
    )


@kpi_bp.route("/insentif")
def insentif():
    periode_id, periode_list = _get_selected_periode()
    items = []
    if periode_id:
        items = (
            Insentif.query.filter_by(periode_id=periode_id)
            .order_by(Insentif.nilai_kpi.desc())
            .all()
        )
    return render_template(
        "kpi/insentif.html", items=items, periode_list=periode_list, periode_id=periode_id
    )


@kpi_bp.route("/insentif/hitung", methods=["POST"])
def hitung_insentif():
    periode_id = request.form.get("periode_id", type=int)
    if not periode_id:
        flash("Pilih periode terlebih dahulu.", "warning")
        return redirect(url_for("kpi.insentif"))

    results = hitung_insentif_untuk_periode(periode_id)
    flash(f"Insentif berhasil dihitung untuk {len(results)} pegawai.", "success")
    return redirect(url_for("kpi.insentif", periode_id=periode_id))
