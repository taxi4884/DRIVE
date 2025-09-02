<?php
// xrechnung_viewer.php
require_once '../includes/bootstrap.php';
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>XRechnung-Viewer</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="css/custom.css">
	<style>
		.upload-area {
			border: 2px dashed #00aaff;
			background-color: #e6f7ff;
			padding: 40px;
			text-align: center;
			border-radius: 8px;
			cursor: pointer;
			transition: background-color 0.3s ease;
		}

		.upload-area.dragover {
			background-color: #d0efff;
		}

		.upload-icon {
			color: #007acc;
		}
	</style>
</head>
<body>
<?php include 'nav.php'; ?>

<main class="container py-5">
    <h1 class="mb-4">XRechnung-Viewer</h1>

	<div class="card mb-4">
		<div class="card-body">
			<div id="uploadArea" class="upload-area">
				<div class="upload-icon mb-3">
					<i class="fas fa-file-upload fa-3x"></i>
				</div>
				<h5>E-Rechnung öffnen</h5>
				<p>XML Datei hierhin ziehen <em>oder</em></p>
				<label class="btn btn-primary mt-2">
					Datei auswählen…
					<input type="file" id="xmlFile" accept=".xml" hidden>
				</label>
			</div>
		</div>
	</div>

    <div id="invoiceDisplay" style="display:none;">
        <div class="card mb-4">
            <div class="card-body" id="invoiceHeader"></div>
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <h5>Positionen</h5>
                <table class="table table-striped" id="invoiceLines">
                    <thead class="table-dark">
                        <tr>
                            <th>Menge</th>
                            <th>Bezeichnung</th>
                            <th>Einzelpreis</th>
                            <th>Gesamtpreis</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-body" id="invoiceTotals"></div>
        </div>

        <form id="pdfForm" method="POST" action="xrechnung_pdf.php" enctype="multipart/form-data" target="_blank" style="display: none;">
			<input type="file" name="xmlFile" id="xmlFileReal" style="display: none;">
			<button type="submit" class="btn btn-success">Schönes PDF für Moni erstellen</button>
		</form>

    </div>
</main>

<!-- Skripte -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
document.getElementById('xmlFile').addEventListener('change', function(event) {
    const file = event.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const parser = new DOMParser();
            const xmlDoc = parser.parseFromString(e.target.result, 'text/xml');
            displayInvoice(xmlDoc);
        }
        reader.readAsText(file);
    }
});

function getElement(xml, path) {
    let parts = path.split('/');
    let node = xml.documentElement;
    for (let part of parts) {
        let children = node.getElementsByTagNameNS('*', part.replace(/^.*:/, ''));
        if (children.length === 0) return null;
        node = children[0];
    }
    return node;
}

/* --------------------------------------------------------------
   1. Syntax erkennen
----------------------------------------------------------------*/
function detectSyntax(xml) {
  const root = xml.documentElement;
  if (root.localName === 'Invoice')           return 'UBL';
  if (root.localName === 'CrossIndustryInvoice') return 'CII';
  return 'UNKNOWN';
}

/* --------------------------------------------------------------
   2. Pfad‑Katalog
----------------------------------------------------------------*/
const PATHS = {
  /* ---- Kopf / Metadata ---- */
  id: {
    CII:['rsm:ExchangedDocument/ram:ID'],
    UBL:['cbc:ID']
  },

  date: {
    CII:['rsm:ExchangedDocument/ram:IssueDateTime/udt:DateTimeString'],
    UBL:['cbc:IssueDate']
  },

  /* ---- Leistungszeitraum ---- */
  periodStart: {
    CII:['rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:BillingSpecifiedPeriod/ram:StartDateTime/udt:DateTimeString'],
    UBL:['cac:InvoicePeriod/cbc:StartDate']
  },
  periodEnd: {
    CII:['rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:BillingSpecifiedPeriod/ram:EndDateTime/udt:DateTimeString'],
    UBL:['cac:InvoicePeriod/cbc:EndDate']
  },

  /* ---- Zahlungsziel / Zahlungsbedingungen ---- */
  paymentTerms: {
    CII:['rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:SpecifiedTradePaymentTerms/ram:Description'],
    UBL:['cac:PaymentTerms/cbc:Note']
  },

  /* ---- Summen & Steuer ---- */
  lineTotal: {
    CII:['rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:SpecifiedTradeSettlementHeaderMonetarySummation/ram:LineTotalAmount'],
    UBL:['cac:LegalMonetaryTotal/cbc:LineExtensionAmount']
  },
  taxBasisTotal: {
    CII:['rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:SpecifiedTradeSettlementHeaderMonetarySummation/ram:TaxBasisTotalAmount'],
    UBL:['cac:LegalMonetaryTotal/cbc:TaxExclusiveAmount']
  },
  taxTotal: {
    CII:['rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:SpecifiedTradeSettlementHeaderMonetarySummation/ram:TaxTotalAmount'],
    UBL:['cac:TaxTotal/cbc:TaxAmount']
  },
  vatRate: {
    CII:['rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:ApplicableTradeTax/ram:RateApplicablePercent'],
    UBL:['cac:TaxTotal/cac:TaxSubtotal/cbc:Percent']
  },
  vatAmount: {
    CII:['rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:ApplicableTradeTax/ram:CalculatedAmount'],
    UBL:['cac:TaxTotal/cac:TaxSubtotal/cbc:TaxAmount']
  },
  grandTotal: {
    CII:['rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:SpecifiedTradeSettlementHeaderMonetarySummation/ram:GrandTotalAmount'],
    /* UBL kann je nach Profil TaxInclusiveAmount oder PayableAmount nutzen */
    UBL:['cac:LegalMonetaryTotal/cbc:TaxInclusiveAmount',
         'cac:LegalMonetaryTotal/cbc:PayableAmount']
  },
  duePayable: {
    CII:['rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:SpecifiedTradeSettlementHeaderMonetarySummation/ram:DuePayableAmount'],
    UBL:['cac:LegalMonetaryTotal/cbc:PayableAmount']
  },

  /* ---- Positionen (Table Lines) ---- */
  positions: {                    // Tag‑Name des Line‑Containers
    CII:'ram:IncludedSupplyChainTradeLineItem',
    UBL:'cac:InvoiceLine'
  },
  posQuantity: {
    CII:['ram:SpecifiedLineTradeDelivery/ram:BilledQuantity'],
    UBL:['cbc:InvoicedQuantity']
  },
  posName: {
    CII:['ram:SpecifiedTradeProduct/ram:Name'],
    UBL:['cac:Item/cbc:Name']
  },
  posPrice: {                      // Einzelpreis
    CII:[
      'ram:SpecifiedLineTradeAgreement/ram:NetPriceProductTradePrice/ram:ChargeAmount',
      'ram:SpecifiedLineTradeAgreement/ram:GrossPriceProductTradePrice/ram:ChargeAmount' // Fallback
    ],
    UBL:['cac:Price/cbc:PriceAmount']
  },
  posTotal: {                      // Gesamtpreis pro Position
    CII:['ram:SpecifiedLineTradeSettlement/ram:SpecifiedTradeSettlementLineMonetarySummation/ram:LineTotalAmount'],
    UBL:['cbc:LineExtensionAmount']
  }
};

/* --------------------------------------------------------------
   3. Helfer: Text holen  (geht alle Kandidatenpfade durch)
----------------------------------------------------------------*/
function getText(xml, syntax, field) {
  const paths = PATHS[field][syntax] || [];
  for (const p of [].concat(paths)) {
    const node = evalPath(xml, p);  // siehe unten
    if (node) return node.textContent.trim();
  }
  return '';
}

/* --------------------------------------------------------------
   4. Mini‑XPath ohne Namensräume
----------------------------------------------------------------*/
function evalPath(startNode, path) {
  // Wenn ein Document übergeben wird, starte bei documentElement,
  // sonst direkt beim Element‑Knoten.
  let node = startNode.nodeType === 9          // Node.DOCUMENT_NODE
             ? startNode.documentElement
             : startNode;

  for (const seg of path.split('/')) {
    const wanted = seg.replace(/^.*:/, '');    // Präfix abschneiden
    node = [...node.children].find(c => c.localName === wanted);
    if (!node) return null;                    // Pfad nicht vorhanden
  }
  return node;
}

// — globale Drop‑Verhinderung —
document.addEventListener('dragover',  e => { e.preventDefault(); e.stopPropagation(); }, false);
document.addEventListener('drop',      e => { e.preventDefault(); e.stopPropagation(); }, false);
/* --------------------------------------------------------------
   5. Anzeige
----------------------------------------------------------------*/
function displayInvoice(xml) {

  const syntax = detectSyntax(xml);
  if (syntax === 'UNKNOWN') {
     alert('Unbekannte XRechnung‑Syntax');
     return;
  }

  /* ---------- Kopf ---------- */
  const invoiceId  = getText(xml, syntax, 'id');
  const rawDate    = getText(xml, syntax, 'date');
  const issueDate  = rawDate.length === 8   // CII‑Kalenderformat “20250115”
        ? `${rawDate.slice(6,8)}.${rawDate.slice(4,6)}.${rawDate.slice(0,4)}`
        : rawDate;                          // UBL already YYYY-MM-DD

  document.getElementById('invoiceHeader').innerHTML = `
      <h5>Rechnungskopf</h5>
      <p><strong>Rechnungsnummer:</strong> ${invoiceId}</p>
      <p><strong>Rechnungsdatum:</strong> ${issueDate}</p>`;

  /* ---------- Positionen ---------- */
  const tbody = document.querySelector('#invoiceLines tbody');
  tbody.innerHTML = '';

  const posTag = PATHS.positions[syntax];
  const positions = xml.getElementsByTagNameNS('*', posTag.split(':').pop());
  for (const p of positions) {
      const quantity = getText(p, syntax, 'posQuantity');
      const name     = getText(p, syntax, 'posName');
      const price    = getText(p, syntax, 'posPrice');
      const total    = getText(p, syntax, 'posTotal');

      tbody.insertAdjacentHTML('beforeend', `
        <tr><td>${quantity}</td><td>${name}</td><td>${price}</td><td>${total}</td></tr>`);
  }

  /* ---------- Summen, Zahlung, … ---------- */
  const paymentTerms = getText(xml, syntax, 'paymentTerms');
  // → analog für Umsatzsteuer & Summen …

  document.getElementById('invoiceTotals').innerHTML = `
      <h5>Summen</h5>
      <p><strong>Zahlungsziel:</strong> ${paymentTerms}</p>`;
  document.getElementById('invoiceDisplay').style.display = 'block';
}

/* --------------------------------------------------------------
   6. Event‑Handler bleibt unverändert
----------------------------------------------------------------*/
document.getElementById('xmlFile').addEventListener('change', e => {
   const file = e.target.files[0];
   if (!file) return;
   const reader = new FileReader();
   reader.onload = ev => {
       const xml = new DOMParser().parseFromString(ev.target.result, 'text/xml');
       displayInvoice(xml);
   };
   reader.readAsText(file);
});

document.getElementById('pdfForm').style.display = 'block';

const uploadArea = document.getElementById('uploadArea');
const fileInput = document.getElementById('xmlFile');

uploadArea.addEventListener('dragover', function(e) {
    e.preventDefault();
    uploadArea.classList.add('dragover');
});

uploadArea.addEventListener('dragleave', function(e) {
    uploadArea.classList.remove('dragover');
});

uploadArea.addEventListener('drop', function(e) {
    e.preventDefault();
    uploadArea.classList.remove('dragover');
    if (e.dataTransfer.files.length > 0) {
        fileInput.files = e.dataTransfer.files;
        const event = new Event('change', { bubbles: true });
        fileInput.dispatchEvent(event);
    }
});
</script>

</body>
</html>
