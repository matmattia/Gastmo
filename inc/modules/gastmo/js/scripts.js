var messenger = {
	'_print': function(msg, c) {
		var that = this, pos = 0,
			divs = $('.message:visible'), l = divs.length, i = 0,
			d = $('<div />').addClass('message alert ' + c).css('opacity', 0).text(msg).click(function() {
				that._close(this);
			});
		if (l > 0) {
			last_d = $(divs[l - 1]);
			pos = last_d.offset().top - $(window).scrollTop() + last_d.outerHeight();
		}
		d.css({
			'display': 'block',
			'top': pos + 'px'
		}).appendTo('body').animate({
			'opacity': 1
		}, 500, 'swing', function() {
			setTimeout(function() {
				that._close(d);
			}, 3000);
		});
	},
	'_close': function(d) {
		$(d).animate({
			'opacity': 0
		}, 500, 'swing', function() {
			$(this).css('display', 'none').remove();
		});
	},
	'success': function(msg) {
		this._print(msg, 'alert-success');
	},
	'error': function(msg) {
		this._print(msg, 'alert-danger');
	}
},

gastmo = {
	'saved_request': {},
	'init': function() {
		var that = this;
		window.addEventListener('beforeunload', function() {
			var k = null;
			for (k in that.saved_request) {
				if (!that.saved_request[k]) {
					return 'Non sono stati salvati correttamente tutti i dati. Sei sicuro di voler lasciare la pagina?';
				}
			}
		});
	},
	'save_request': {},
	'saveRequest': function(id, opts, buttons) {
		var that = this;
		if (this.save_request[id]) {
			this.save_request[id].abort();
		}
		this.setSavedRequest(id, false);
		this.loading(true);
		this.toggleButtons(buttons, false);
		this.save_request[id] = $.ajax($.extend({}, {
			'dataType': 'json',
			'complete': function() {
				that.loading(false);
				that.toggleButtons(buttons, true);
			},
			'success': function(r) {
				if (r && r.ok && r.ok == 1) {
					that.setSavedRequest(id, true);
					messenger.success(r.msg || 'Salvataggio avvenuto correttamente.');
				} else {
					messenger.error(r.msg || 'Ci sono stati dei problemi.');
				}
			},
			'error': function() {
				messenger.error('Ci sono stati dei problemi.');
			}
		}, opts || {}));
	},
	'setSavedRequest': function(id, saved) {
		this.saved_request[id] = !!saved;
	},
	'loading': function(print) {
		var l = document.getElementById('loading'), s = null;
		if (!l) {
			l = document.createElement('div');
			l.setAttribute('id', 'loading');
			l.setAttribute('role', 'status');
			l.classList.add('spinner-border', 'm-2');
			s = document.createElement('span');
			s.classList.add('visually-hidden');
			s.innerText = 'Loading...';
			document.body.appendChild(l);
		}
		if (print) {
			l.classList.remove('d-none');
		} else {
			l.classList.add('d-none');
		}
	},
	'toggleButtons': function(buttons, enable) {
		buttons = $(buttons);
		if (buttons.length > 0) {
			buttons.prop('disabled', !enable);
		}
	},
	'math': function(a, op, b, m) {
		var n = null, l = 0, i = 0;
		if (!isNaN(a)) {
			n = new Big(a);
			switch (op) {
				case 'minus':
				case '-':
					n = n.minus(b);
				break;
				case 'mod':
				case '%':
					n = n.mod(b);
				break;
				case 'plus':
				case '+':
					n = n.plus(b);
				break;
				case 'round':
					n = n.round(b);
				break;
				case 'times':
				case '*':
					n = n.times(b);
				break;
			}
			if (typeof m == 'object') {
				l = m.length;
				for (i = 0; i < l; i++) {
					n = this.math(n, m[i][0], m[i][1]);
				}
			}
		}
		return n;
	}
},

order = {
	'products': {},
	'base_path': null,
	'id_order': null,
	'init': function() {
		var that = this,
			inps = document.querySelectorAll('#order .user_ordered input'), l = inps.length, i = 0, notes = document.querySelectorAll('#order .note'),
			save_order = document.getElementById('save_order'), toggle_ordered_products = document.getElementById('toggle_ordered_products'), category_togglers = document.querySelectorAll('#order td.category button');
		for (i = 0; i < l; i++) {
			inps[i].addEventListener('change', function() {
				var qty = this.value, d = null, tot = 0, total_values = null, l = 0, i = 0, v = null;
				that.resetRow(this);
				qty = qty.trim() == '' ? 0 : qty.replace(/,/g, '.');
				if (qty == null || isNaN(qty)) {
					that.setInputError(this);
				} else {
					that.setInputError(this, true);
					d = that.getRowData(this);
					if (d) {
						d.ordered = gastmo.math(d.ordered, '+', qty, [['-', d.user_ordered]]);
						that.setRowValue(this, 'ordered', d.ordered);
						that.setRowValue(this, 'completed', that.getCalcValue(d, 'completed'));
						that.setRowValue(this, 'to_complete', that.getCalcValue(d, 'to_complete'));
						that.setRowValue(this, 'price_total', gastmo.math(d.price, '*', qty, [['round', 2]]));
						total_values = document.querySelectorAll('td.price_total');
						l = total_values.length;
						for (i = 0; i < l; i++) {
							v = total_values[i].getAttribute('data-val');
							if (!isNaN(v)) {
								tot = gastmo.math(tot, '+', v);
							}
						}
						document.getElementById('price_total_order').textContent = that.formatCurrency(tot);
					} else {
						that.setInputError(this);
					}
				}
				that.save();
			});
			inps[i].addEventListener('focus', function() {
				var tr = this.closest('tr');
				if (tr) {
					tr.classList.add('table-active');
				}
			});
			inps[i].addEventListener('blur', function() {
				var tr = this.closest('tr');
				if (tr) {
					tr.classList.remove('table-active');
				}
			});
		}
		l = notes.length;
		for (i = 0; i < l; i++) {
			new bootstrap.Tooltip(notes[i], {
				'container': 'body',
				'placement': 'right'
			});
		}
		if (save_order) {
			save_order.addEventListener('click', function(e) {
				e.preventDefault();
				that.save();
			});
		}
		if (toggle_ordered_products) {
			toggle_ordered_products.addEventListener('click', function(e) {
				var trs = document.querySelectorAll('#order tr[id^="product"].d-none'), l = trs.length, i = 0;
				e.preventDefault();
				if (l > 0) {
					this.textContent = 'Mostra prodotti ordinati';
					for (i = 0; i < l; i++) {
						trs[i].classList.remove('d-none');
					}
				} else {
					this.textContent = 'Mostra tutti i prodotti';
					that.toggleButtons(false);
					$.ajax({
						'url': that.base_path + '/order/getorderedproducts/' + that.getOrderId() + '/',
						'type': 'post',
						'data': {
							'sent': 1
						},
						'dataType': 'json',
						'complete': function() {
							that.toggleButtons(true);
						},
						'success': function(d) {
							var trs = null, l = 0, i = 0;
							if (d && d.ok && d.ok == 1) {
								trs = document.querySelectorAll('#order tr[id^="product"]');
								l = trs.length;
								for (i = 0; i < l; i++) {
									if (d.products.indexOf(parseInt(trs[i].getAttribute('id').substr(8), 10)) == -1) {
										trs[i].classList.add('d-none');
									}
								}
							} else {
								messenger.error('Ci sono stati dei problemi.');
							}
						},
						'error': function() {
							messenger.error('Ci sono stati dei problemi.');
						}
					});
				}
			});
		}
		l = category_togglers.length;
		for (i = 0; i < l; i++) {
			category_togglers[i].addEventListener('click', function() {
				var c = {
					'open': 'bi-dash-lg',
					'closed': 'bi-plus-lg'
				}, trs = document.querySelectorAll('#order tr[data-category="' + this.getAttribute('data-category') + '"]'), l = trs.length, i = 0, open = this.getAttribute('data-category-closed') == 1, icon = this.querySelector('.bi');
				icon.classList.remove(open ? c.closed : c.open);
				icon.classList.add(open ? c.open : c.closed);
				for (i = 0; i < l; i++) {
					if (open) {
						trs[i].classList.remove('d-none');
					} else {
						trs[i].classList.add('d-none');
					}
				}
				this.setAttribute('data-category-closed', open ? 0 : 1)
			});
		}
	},
	'getOrderId': function() {
		if (!this.id_order) {
			this.id_order = $('#order').attr('data-order');
			if (isNaN(this.id_order) || this.id_order <= 0) {
				this.id_order = null;
			}
		}
		return this.id_order;
	},
	'getRow': function(inp) {
		return $(inp).closest('tr');
	},
	'getRowId': function(inp) {
		var d = this.getRowJson(inp);
		return d ? d.id : null;
	},
	'getRowData': function(inp) {
		var d = this.getRowJson(inp), data = null;
		if (d) {
			if (this.products[d.id]) {
				data = this.products[d.id];
			} else {
				d.completed = this.getCalcValue(d, 'completed')
				d.to_complete = this.getCalcValue(d, 'to_complete');
				d.price_total = Math.round(d.price * 0);
				this.products[d.id] = data = d;
			}
		}
		return data;
	},
	'getRowJson': function(inp) {
		var j = null, r = this.getRow(inp);
		if (r.length > 0) {
			try {
				j = $.parseJSON(r.attr('data-product'));
			} catch (e) {	
			}
		}
		return j;
	},
	'getRowValue': function(inp, f){
		var d = this.getRowData(inp);
		return d[f] || d[f] === 0 ? d[f] : null;
	},
	'resetRow': function(inp) {
		var id = this.getRowId(inp);
		if (id) {
			this.products[id] = null;
		}
	},
	'setRowValue': function(inp, f, v) {
		var id = this.getRowId(inp),
			tr = null, td = null;
		if (id) {
			inp = $(inp);
			this.products[id][f] = v;
			if (['ordered', 'completed', 'to_complete', 'price_total'].indexOf(f) >= 0) {
				tr = this.getRow(inp);
				if (tr) {
					td = tr.find('td.' + f);
					if (td.length > 0) {
						if (f == 'price_total') {
							td.attr('data-val', v);
							td.text(v > 0 || $.trim(inp.val()) != '' ? this.formatCurrency(v) : '');
						} else {
							td.text(v);
						}
						switch (f) {
							case 'to_complete':
								if (v == 0) {
									td.addClass('full');
								} else {
									td.removeClass('full');
								}
							break;
							case 'completed':
								var ord = this.getRowValue(inp, 'ordered');
								if (v == 0 && ord > 0 && ord / this.getRowValue(inp, 'qty_package') < 0.5) {
									td.addClass('low');
								} else {
									td.removeClass('low');
								}
							break;
						}
					}
				}
			}
		}
	},
	'setInputError': function(inp, remove) {
		if (remove) {
			inp.classList.remove('is-invalid');
		} else {
			inp.classList.add('is-invalid');
		}
	},
	'getCalcValue': function(d, f) {
		var res = null, x = null;
		switch (f) {
			case 'completed':
				res = d.ordered <= 0 ? 0 : Math.floor(d.ordered / d.qty_package);
			break;
			case 'to_complete':
				x = gastmo.math(d.ordered, '%', d.qty_package);
				res = x > 0 ? gastmo.math(d.qty_package, '-', x) : 0;
			break;
		}
		return res;
	},
	'formatCurrency': function(v) {
		v = isNaN(v) ? 0 : parseFloat(v);
		return '€ ' + v.toFixed(2).replace(/\./g, ',');
	},
	'save': function() {
		var products = {},
			inps = $('#order .user_ordered input'), l = inps.length, i = 0,
			v = null, pid = null;
		for (i = 0; i < l; i++) {
			v = $(inps[i]).val();
			if (v) {
				pid = this.getRowId(inps[i]);
				if (pid) {
					products[pid] = v;
				}
			}
		}
		gastmo.saveRequest('order-save', {
			'url': this.base_path + '/order/save/' + this.getOrderId() + '/',
			'type': 'post',
			'data': {
				'sent': 1,
				'products': products
			}
		}, '#save_order,#toggle_ordered_products');
	},
	'toggleButtons': function(enable) {
		$('#save_order,#toggle_ordered_products').prop('disabled', !enable);
	}
},

order_delivered = {
	'id_request': 'order-save-votes',
	'init': function() {
		var that = this;
		$('#order_form').submit(function() {
			var f = $(this);
			if (that.checkInputs()) {
				gastmo.saveRequest(that.id_request, {
					'url': f.attr('action'),
					'type': f.attr('method'),
					'data': f.serialize()
				}, '#order_form button');
			}
			return false;
		});
		$('#order .vote').mouseleave(function() {
			var t = $(this), v = t.find(':input[name^="product_vote"]').val();
			that.showVote(t.find('a'), false);
			if (!isNaN(v) && v > 0) {
				that.showVote(t.find('a:lt(' + v + ')'), true);
			}
		}).find('a').click(function() {
			var a = $(this);
			that.setVote(a.attr('data-product'), a.attr('data-vote'));
			return false;
		}).mouseenter(function() {
			var s = $(this);
			that.showVote(s.add(s.prevAll('a')), true);
			that.showVote(s.nextAll('a'), false);
		}).tooltip({
			'placement': 'top'
		});
	},
	'showVote': function(a, v) {
		var c = ['bi-star', 'bi-star-fill']
		$(a).find('.bi').addClass(v ? c[1] : c[0]).removeClass(v ? c[0] : c[1]);
	},
	'setVote': function(p, v) {
		$(':input[name="product_vote[' + p +']"]').val(v);
		gastmo.setSavedRequest(this.id_request, false);
	},
	'checkInputs': function() {
		var c = true, inps = $(':input[name^="product_vote["]'), l = inps.length, i = 0;
		for (i = 0; i < l; i++) {
			if (!this.checkInput(inps[i])) {
				c = false;
			}
		}
		if (!c) {
			messenger.error('Devi inserire lo scarto e una nota per i prodotti con reclami.');
		}
		return c;
	},
	'checkInput': function(inp) {
		var c = true, inps = null, l = 0, i = 0, inp_s = null;
		inp = $(inp);
		if (inp.val() == 1) {
			inps = inp.closest('tr').find(':input[name^="product_vote_descr["], :input[name^="product_waste["]');
			l = inps.length;
			for (i = 0; i < l; i++) {
				inp_s = $(inps[i]);
				if (inp_s.val()) {
					inp_s.closest('td').removeClass('has-error');
				} else {
					inp_s.closest('td').addClass('has-error');
					c = false;
				}
			}
		}
		return c;
	}
};

window.addEventListener('DOMContentLoaded', function() {
	gastmo.init();
	order.init();
	order_delivered.init();
});