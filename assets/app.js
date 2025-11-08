function toggleSidebar(forceClose) {
  var sidebar = document.getElementById('sidebar');
  var overlay = document.getElementById('sidebarOverlay');
  if (!sidebar || !overlay) return;

  var isOpen = sidebar.classList.contains('open');

  if (forceClose === true) {
    sidebar.classList.remove('open');
    overlay.classList.remove('active');
    return;
  }

  if (isOpen) {
    sidebar.classList.remove('open');
    overlay.classList.remove('active');
  } else {
    sidebar.classList.add('open');
    overlay.classList.add('active');
  }
}

document.addEventListener('DOMContentLoaded', function () {
  var overlay = document.getElementById('sidebarOverlay');
  if (overlay) {
    overlay.addEventListener('click', function () {
      toggleSidebar(true);
    });
  }


  document.addEventListener('click', function (e) {
    var el = e.target.closest('.tx-desc-short');
    if (!el) return;

    var full = el.getAttribute('data-full-description') || '';
    if (!full) return;

    alert(full);
  });

  document.querySelectorAll('[data-bank-select]').forEach(function (wrapper) {
    var target = wrapper.getAttribute('data-linked-payment') || 'payment_method';
    var form = wrapper.closest('form') || document;
    var radios = form.querySelectorAll('input[name="' + target + '"]');
    if (!radios.length) return;
    var select = wrapper.querySelector('select');

    function refreshBankVisibility() {
      var selected = form.querySelector('input[name="' + target + '"]:checked');
      var isBank = selected && selected.value === 'bank';
      wrapper.classList.toggle('hidden', !isBank);
      if (select) {
        select.disabled = !isBank || select.options.length === 0;
        if (!isBank) {
          select.value = '';
        }
      }
    }

    radios.forEach(function (radio) {
      radio.addEventListener('change', refreshBankVisibility);
    });

    refreshBankVisibility();
  });

  var financeModal = document.getElementById('financeModal');
  var financeBackdrop = document.getElementById('financeModalBackdrop');
  var financeFields = {
    title: document.getElementById('financeModalFieldTitle'),
    date: document.getElementById('financeModalDate'),
    cost: document.getElementById('financeModalFieldCost'),
    expense: document.getElementById('financeModalFieldExpense'),
    income: document.getElementById('financeModalFieldIncome'),
    method: document.getElementById('financeModalFieldMethod'),
    net: document.getElementById('financeModalFieldNet'),
    category: document.getElementById('financeModalFieldCategory'),
    bank: document.getElementById('financeModalFieldBank'),
    creator: document.getElementById('financeModalFieldCreator'),
    description: document.getElementById('financeModalFieldDescription'),
  };

  function formatCurrency(symbol, value) {
    var num = Number(value) || 0;
    return symbol + num.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  function closeFinanceModal() {
    if (financeModal) financeModal.classList.remove('open');
    if (financeBackdrop) financeBackdrop.classList.remove('active');
  }

  function openFinanceModal(entry, symbol) {
    if (!financeModal || !financeBackdrop) return;
    financeBackdrop.classList.add('active');
    financeModal.classList.add('open');

    if (financeFields.title) financeFields.title.textContent = entry.title || 'Untitled';
    if (financeFields.date) financeFields.date.textContent = entry.date || entry.raw_date || '';
    if (financeFields.cost) financeFields.cost.textContent = formatCurrency(symbol, entry.cost);
    if (financeFields.expense) financeFields.expense.textContent = formatCurrency(symbol, entry.expense);
    if (financeFields.income) financeFields.income.textContent = formatCurrency(symbol, entry.income);
    if (financeFields.method) financeFields.method.textContent = entry.method || 'Cash';
    if (financeFields.net) {
      financeFields.net.textContent = formatCurrency(symbol, entry.net);
      financeFields.net.classList.toggle('text-success', entry.net >= 0);
      financeFields.net.classList.toggle('text-danger', entry.net < 0);
    }
    if (financeFields.category) financeFields.category.textContent = entry.category || 'No category';
    if (financeFields.bank) financeFields.bank.textContent = entry.bank || 'No bank';
    if (financeFields.creator) financeFields.creator.textContent = entry.creator || 'Unknown';
    if (financeFields.description) financeFields.description.textContent = entry.description || 'No description provided.';

    var deleteButton = document.getElementById('financeModalDelete');
    if (deleteButton) {
      if (entry.delete_url) {
        deleteButton.style.display = '';
        deleteButton.onclick = function () {
          if (confirm('Delete this finance entry? This cannot be undone.')) {
            window.location.href = entry.delete_url;
          }
        };
      } else {
        deleteButton.style.display = 'none';
      }
    }
  }

  document.addEventListener('click', function (e) {
    var row = e.target.closest('.finance-row');
    if (!row) return;

    var payload = row.getAttribute('data-finance-entry');
    if (!payload) return;
    try {
      var parsed = JSON.parse(payload);
      openFinanceModal(parsed, row.getAttribute('data-currency') || '$');
    } catch (err) {
      console.error('Unable to open finance entry modal', err);
    }
  });

  var financeCloseButtons = [
    document.getElementById('financeModalClose'),
    document.getElementById('financeModalCloseFooter')
  ];
  financeCloseButtons.forEach(function (btn) {
    if (btn) {
      btn.addEventListener('click', function () {
        closeFinanceModal();
      });
    }
  });

  if (financeBackdrop) {
    financeBackdrop.addEventListener('click', closeFinanceModal);
  }

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      closeFinanceModal();
    }
  });
});
