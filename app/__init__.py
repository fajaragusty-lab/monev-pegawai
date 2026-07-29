import os

from flask import Flask

from config import Config
from app.extensions import db, login_manager


def create_app(config_class=Config):
    app = Flask(__name__)
    app.config.from_object(config_class)

    os.makedirs(app.config["UPLOAD_FOLDER"], exist_ok=True)

    db.init_app(app)
    login_manager.init_app(app)

    from app.models import User

    @login_manager.user_loader
    def load_user(user_id):
        return db.session.get(User, int(user_id))

    # Register blueprints
    from app.auth.routes import auth_bp
    from app.master.routes import master_bp
    from app.penugasan.routes import penugasan_bp
    from app.lapangan.routes import lapangan_bp
    from app.verifikasi.routes import verifikasi_bp
    from app.kpi.routes import kpi_bp
    from app.laporan.routes import laporan_bp

    app.register_blueprint(auth_bp)
    app.register_blueprint(master_bp, url_prefix="/master")
    app.register_blueprint(penugasan_bp, url_prefix="/penugasan")
    app.register_blueprint(lapangan_bp, url_prefix="/lapangan")
    app.register_blueprint(verifikasi_bp, url_prefix="/verifikasi")
    app.register_blueprint(kpi_bp, url_prefix="/kpi")
    app.register_blueprint(laporan_bp, url_prefix="/laporan")

    from flask import redirect, url_for, send_from_directory
    from flask_login import login_required, current_user

    @app.route("/")
    @login_required
    def index():
        return redirect(url_for("dashboard"))

    @app.route("/uploads/<path:filename>")
    @login_required
    def uploaded_file(filename):
        return send_from_directory(app.config["UPLOAD_FOLDER"], filename)

    @app.route("/dashboard")
    @login_required
    def dashboard():
        from app.models import (
            Penugasan,
            PelaksanaanLapangan,
            Pegawai,
            WajibPajak,
            KPIPegawai,
        )
        from flask import render_template

        stats = {
            "total_penugasan": Penugasan.query.count(),
            "penugasan_active": Penugasan.query.filter_by(status="active").count(),
            "penugasan_completed": Penugasan.query.filter_by(status="completed").count(),
            "pending_verifikasi": PelaksanaanLapangan.query.filter_by(status="submitted").count(),
            "total_pegawai": Pegawai.query.filter_by(aktif=True).count(),
            "total_wajib_pajak": WajibPajak.query.count(),
            "total_kpi": KPIPegawai.query.count(),
        }

        my_stats = None
        if not current_user.is_admin() and current_user.pegawai:
            pegawai = current_user.pegawai
            my_stats = {
                "tugas_aktif": Penugasan.query.filter_by(
                    pegawai_id=pegawai.id, status="active"
                ).count(),
                "tugas_selesai": Penugasan.query.filter_by(
                    pegawai_id=pegawai.id, status="completed"
                ).count(),
            }

        return render_template("dashboard.html", stats=stats, my_stats=my_stats)

    # Error handlers
    from flask import render_template

    @app.errorhandler(403)
    def forbidden(e):
        return render_template("errors/403.html"), 403

    @app.errorhandler(404)
    def not_found(e):
        return render_template("errors/404.html"), 404

    # Template filters
    @app.template_filter("rupiah")
    def rupiah_filter(value):
        try:
            value = float(value)
        except (TypeError, ValueError):
            return value
        return "Rp {:,.0f}".format(value).replace(",", ".")

    @app.template_filter("datefmt")
    def datefmt_filter(value, fmt="%d-%m-%Y %H:%M"):
        if value is None:
            return "-"
        return value.strftime(fmt)

    return app
