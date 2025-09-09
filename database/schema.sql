CREATE TABLE company (
    company_name TEXT,
    abn TEXT,
    nmi_mirn TEXT,
    site_address TEXT,
    suburb TEXT,
    postcode TEXT
);

CREATE TABLE invoice (
    period TEXT,
    nmi_number TEXT,
    location2 TEXT,
    invoice TEXT,
    ex_gst REAL,
    gst REAL,
    total REAL
);
