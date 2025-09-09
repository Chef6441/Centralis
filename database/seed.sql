-- Example data for Centralis schema
INSERT INTO company (company_name, abn, nmi_mirn, site_address, suburb, postcode) VALUES
  ('Acme Pty Ltd', '12345678901', 'NMI123', '1 Main St', 'Sydney', '2000'),
  ('Globex Corporation', '98765432109', 'NMI987', '42 Enterprise Rd', 'Melbourne', '3000');

INSERT INTO invoice (company_id, period, nmi_number, location2, invoice, ex_gst, gst, total) VALUES
  (1, '2024-01', 'NMI123', 'Warehouse', 'INV-001', 1000.00, 100.00, 1100.00),
  (1, '2024-02', 'NMI123', 'Warehouse', 'INV-002', 1200.00, 120.00, 1320.00),
  (2, '2024-01', 'NMI987', 'Office', 'INV-003', 900.00, 90.00, 990.00);
