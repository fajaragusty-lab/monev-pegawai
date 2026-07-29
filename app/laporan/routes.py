import io
from datetime import datetime

from flask import Blueprint, Response, render_template, request, send_file
from flask_login import login_required
from openpyxl import Workbook
from openpyxl.styles import Font
from reportlab.lib import colors
from reportlab.lib.pagesizes import A4, landscape
from reportlab.lib.styles import getSampleStyleSheet
from reportlab.lib.units import cm
from reportlab.platypus import Paragraph, SimpleDocTemplate, Spacer, Table, TableStyle

from app.decorators import admin_required
from app.models import (
    Insentif,
    KPIPegawai,
    Pegawai,
    PelaksanaanLapangan,
    Penugasan,
    TargetPeriode,
    Verifikasi,
)

laporan_bp = Blueprint("laporan", __name__)


@laporan_bp.before_request
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


@laporan_bp.route("/")
def index():
    return render_template("laporan/index.html")


@laporan_bp.route("/dashboard-monitoring")
def dashboard_monitoring():
    total_penugasan = Penugasan.query.count()
    stats = {
        "total_penugasan": total_penugasan,
        "draft": Penugasan.query.filter_by(status="draft").count(),
        "active": Penugasan.query.filter_by(status="active").count(),
        "completed": Penugasan.query.filter_by(status="completed").count(),
        "cancelled": Penugasan.query.filter_by(status="cancelled").count(),
        "pending_verifikasi": PelaksanaanLapangan.query.filter_by(status="submitted").count(),
        "approved": Verifikasi.query.filter_by(status="approved").count(),
        "rejected": Verifikasi.query.filter_by(status="rejected").count(),
        "total_pegawai": Pegawai.query.filter_by(aktif=True).count(),
    }
    return render_template("laporan/dashboard_monitoring.html", stats=stats)


@laporan_bp.route("/kpi")
def laporan_kpi():
    periode_id, periode_list = _get_selected_periode()
    items = []
    if periode_id:
        items = (
            KPIPegawai.query.filter_by(periode_id=periode_id)
            .order_by(KPIPegawai.nilai_kpi_total.desc())
            .all()
        )
    return render_template(
        "laporan/laporan_kpi.html", items=items, periode_list=periode_list, periode_id=periode_id
    )


@laporan_bp.route("/penagihan")
def laporan_penagihan():
    status = request.args.get("status", "")
    query = Penugasan.query
    if status:
        query = query.filter_by(status=status)
    items = query.order_by(Penugasan.created_at.desc()).all()
    return render_template("laporan/laporan_penagihan.html", items=items, current_status=status)


@laporan_bp.route("/gps")
def laporan_gps():
    items = PelaksanaanLapangan.query.order_by(
        PelaksanaanLapangan.tanggal_pelaksanaan.desc()
    ).all()
    return render_template("laporan/laporan_gps.html", items=items)


@laporan_bp.route("/insentif")
def laporan_insentif():
    periode_id, periode_list = _get_selected_periode()
    items = []
    if periode_id:
        items = (
            Insentif.query.filter_by(periode_id=periode_id)
            .order_by(Insentif.nilai_kpi.desc())
            .all()
        )
    return render_template(
        "laporan/laporan_insentif.html", items=items, periode_list=periode_list, periode_id=periode_id
    )


@laporan_bp.route("/grafik")
def grafik():
    periode_id, periode_list = _get_selected_periode()
    items = []
    if periode_id:
        items = (
            KPIPegawai.query.filter_by(periode_id=periode_id)
            .order_by(KPIPegawai.nilai_kpi_total.desc())
            .all()
        )
    labels = [item.pegawai.nama for item in items]
    values = [item.nilai_kpi_total for item in items]
    return render_template(
        "laporan/grafik.html",
        periode_list=periode_list,
        periode_id=periode_id,
        labels=labels,
        values=values,
    )


# ---------------------------------------------------------------------------
# Export PDF
# ---------------------------------------------------------------------------
@laporan_bp.route("/export/pdf")
def export_pdf():
    periode_id, _ = _get_selected_periode()
    periode = TargetPeriode.query.get(periode_id) if periode_id else None

    items = []
    if periode_id:
        items = (
            KPIPegawai.query.filter_by(periode_id=periode_id)
            .order_by(KPIPegawai.nilai_kpi_total.desc())
            .all()
        )

    buffer = io.BytesIO()
    doc = SimpleDocTemplate(buffer, pagesize=landscape(A4))
    styles = getSampleStyleSheet()
    elements = []

    title = "Laporan KPI Petugas Penagihan Pajak"
    if periode:
        title += f" - Periode {periode.periode}"
    elements.append(Paragraph(title, styles["Title"]))
    elements.append(Spacer(1, 0.5 * cm))
    elements.append(
        Paragraph(f"Dicetak: {datetime.utcnow().strftime('%d-%m-%Y %H:%M')} UTC", styles["Normal"])
    )
    elements.append(Spacer(1, 0.5 * cm))

    data = [
        [
            "Rank",
            "Nama Pegawai",
            "Target",
            "Realisasi",
            "Penagihan",
            "Verval",
            "GPS",
            "Ketepatan",
            "Dokumen",
            "Total KPI",
        ]
    ]
    for idx, item in enumerate(items, start=1):
        data.append(
            [
                str(idx),
                item.pegawai.nama,
                f"{item.nilai_target:.1f}",
                f"{item.nilai_realisasi:.1f}",
                f"{item.nilai_penagihan:.1f}",
                f"{item.nilai_verval:.1f}",
                f"{item.nilai_gps:.1f}",
                f"{item.nilai_ketepatan:.1f}",
                f"{item.nilai_dokumen:.1f}",
                f"{item.nilai_kpi_total:.2f}",
            ]
        )

    if len(data) == 1:
        data.append(["-", "Belum ada data KPI", "-", "-", "-", "-", "-", "-", "-", "-"])

    table = Table(data, repeatRows=1)
    table.setStyle(
        TableStyle(
            [
                ("BACKGROUND", (0, 0), (-1, 0), colors.HexColor("#0d6efd")),
                ("TEXTCOLOR", (0, 0), (-1, 0), colors.white),
                ("FONTNAME", (0, 0), (-1, 0), "Helvetica-Bold"),
                ("FONTSIZE", (0, 0), (-1, -1), 8),
                ("GRID", (0, 0), (-1, -1), 0.5, colors.grey),
                ("ROWBACKGROUNDS", (0, 1), (-1, -1), [colors.white, colors.HexColor("#f2f2f2")]),
                ("ALIGN", (0, 0), (-1, -1), "CENTER"),
            ]
        )
    )
    elements.append(table)
    doc.build(elements)
    buffer.seek(0)

    filename = f"laporan_kpi_{periode.periode if periode else 'all'}.pdf"
    return send_file(
        buffer, mimetype="application/pdf", as_attachment=True, download_name=filename
    )


# ---------------------------------------------------------------------------
# Export Excel
# ---------------------------------------------------------------------------
@laporan_bp.route("/export/excel")
def export_excel():
    periode_id, _ = _get_selected_periode()
    periode = TargetPeriode.query.get(periode_id) if periode_id else None

    items = []
    if periode_id:
        items = (
            KPIPegawai.query.filter_by(periode_id=periode_id)
            .order_by(KPIPegawai.nilai_kpi_total.desc())
            .all()
        )

    wb = Workbook()
    ws = wb.active
    ws.title = "Laporan KPI"

    header = [
        "Rank",
        "Nama Pegawai",
        "NIP",
        "Nilai Target",
        "Nilai Realisasi",
        "Nilai Penagihan",
        "Nilai Verval",
        "Nilai GPS",
        "Nilai Ketepatan",
        "Nilai Dokumen",
        "Total Nilai KPI",
    ]
    ws.append(header)
    for cell in ws[1]:
        cell.font = Font(bold=True)

    for idx, item in enumerate(items, start=1):
        ws.append(
            [
                idx,
                item.pegawai.nama,
                item.pegawai.nip,
                item.nilai_target,
                item.nilai_realisasi,
                item.nilai_penagihan,
                item.nilai_verval,
                item.nilai_gps,
                item.nilai_ketepatan,
                item.nilai_dokumen,
                item.nilai_kpi_total,
            ]
        )

    for column_cells in ws.columns:
        length = max(len(str(cell.value)) if cell.value is not None else 0 for cell in column_cells)
        ws.column_dimensions[column_cells[0].column_letter].width = max(12, length + 2)

    buffer = io.BytesIO()
    wb.save(buffer)
    buffer.seek(0)

    filename = f"laporan_kpi_{periode.periode if periode else 'all'}.xlsx"
    return send_file(
        buffer,
        mimetype="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
        as_attachment=True,
        download_name=filename,
    )
