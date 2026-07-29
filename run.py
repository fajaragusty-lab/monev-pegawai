"""Entry point for the Sistem Penilaian Kinerja Petugas Penagihan Pajak app.

Running `python run.py` will:
1. Create all database tables (if they do not already exist).
2. Seed demo data if the database is empty.
3. Start the Flask development server on http://localhost:5000
"""

from app import create_app
from app.extensions import db
from app.models import User

app = create_app()

with app.app_context():
    db.create_all()

    if User.query.first() is None:
        from seed import seed_data

        seed_data()


if __name__ == "__main__":
    app.run(host="0.0.0.0", port=5000, debug=True)
