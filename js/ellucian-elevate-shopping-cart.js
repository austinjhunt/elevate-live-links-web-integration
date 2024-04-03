/* 
This JavaScript is used by pages published from Cascade CMS using the Ellucian Elevate Continuing Education Accordions component 

It includes logic primarily for Elevate shopping cart management, and for showing
a modal with program instance details.

It depends on jquery-modal and jquery-toast.

This file lives in / is published from Cascade CMS at 
https://cofc.cascadecms.com/entity/open.act?id=3e2b489dac1e002e0d8b890f9d5a27c8&type=file

It is included as a <script> after jquery-toast and jquery-modal in the js-body-end format. 
https://cofc.cascadecms.com/entity/open.act?id=2e923a04ac1e002e073f6cb49a125f72&type=format 

*/

var ELEVATE_ENVIRONMENT, CART_PROGRAM_INSTANCES_SESSION_KEY, ELEVATE_URL, ELEVATE_SHOP_URL;
// CHANGE TO PROD WHEN READY
ELEVATE_ENVIRONMENT = 'test';
let url = new URL(window.location.href);
CART_PROGRAM_INSTANCES_SESSION_KEY = `${url.hostname + url.pathname}-cart-program-instances`;
const ELEVATE_URLS = {
    'test': 'https://us-elevate-nonprod.elluciancloud.com/app/cocha',
    'prod': 'https://us-elevate.elluciancloud.com/app/cocha'
};
ELEVATE_SHOP_URL = `${ELEVATE_URLS[ELEVATE_ENVIRONMENT]}/!solar.qsl.addtocart`;
const getSessionCartProgramInstances = () => {
    // get existing cart program instances from session storage  
    let cartProgramInstances = JSON.parse(sessionStorage.getItem(CART_PROGRAM_INSTANCES_SESSION_KEY)) || [];
    return cartProgramInstances;
}
const updateSessionCartProgramInstances = (instances) => {
    // update cart program instances in session storage 
    sessionStorage.setItem(CART_PROGRAM_INSTANCES_SESSION_KEY, JSON.stringify(instances));
}

const generateSectionsListHTML = (sections) => {
    console.log({ 'generateSectionsListHTML': sections });
    let friendlyTutorialTime12HourAMPM = (tutorial) => {

        let {
            tutorial: {
                tutorialtime
            }
        } = tutorial;
        // time argument format: 18:00 - 21:00 
        // output format: 6:00 PM - 9:00 PM
        let [startTime, endTime] = tutorialtime.split(' - ');
        let [startHour, startMinute] = startTime.split(':');
        let [endHour, endMinute] = endTime.split(':');
        let startAMPM = startHour >= 12 ? 'PM' : 'AM';
        let endAMPM = endHour >= 12 ? 'PM' : 'AM';
        let friendlyStartHour = startHour > 12 ? startHour - 12 : startHour;
        let friendlyEndHour = endHour > 12 ? endHour - 12 : endHour;
        return `${friendlyStartHour}:${startMinute} ${startAMPM} - ${friendlyEndHour}:${endMinute} ${endAMPM}`;
    }
    let friendlySectionFeeTotal = (section) => {
        let feeTotal = section.fees.reduce((acc, fee) => acc + fee.fee.amount, 0);
        return feeTotal;
    }
    let friendlyDaysOfTheWeek = (tutorial) => {
        let { tutorial: { daysOfTheWeek } } = tutorial;
        daysOfTheWeek = daysOfTheWeek.replace('Monday', 'M');
        daysOfTheWeek = daysOfTheWeek.replace('Tuesday', 'T');
        daysOfTheWeek = daysOfTheWeek.replace('Wednesday', 'W');
        daysOfTheWeek = daysOfTheWeek.replace('Thursday', 'R');
        daysOfTheWeek = daysOfTheWeek.replace('Friday', 'F');
        daysOfTheWeek = daysOfTheWeek.replace('Saturday', 'S');
        daysOfTheWeek = daysOfTheWeek.replace('Sunday', 'U');
        daysOfTheWeek = daysOfTheWeek.replace(', ', '');
        return daysOfTheWeek;
    }
    let friendlyTutorialDateRange = (tutorial) => {
        // startDate format: 05-MAR-2025 
        // endDate format: 05-MAR-2025
        let { tutorial: {
            startDate, endDate
        }
        } = tutorial;

        //output M D, YYYY - M D, YYYY
        let startDateObj = new Date(startDate);
        let endDateObj = new Date(endDate);

        let startDateString = startDateObj.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
        let endDateString = endDateObj.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
        return `${startDateString} - ${endDateString}`;
    }

    let generateTutorialTutorHTML = (tutorial) => {
        let { tutorial: { tutor } } = tutorial;
        return `<div class="card-icon"><i class="brei-icon fa-sm fa-regular fa-chalkboard-user"></i><span>${tutor}</span></div>`;
    }

    let generateTutorialMeetingTimesHTML = (tutorial) => {
        let daysOfTheWeek = friendlyDaysOfTheWeek(tutorial);
        let meetingTime = friendlyTutorialTime12HourAMPM(tutorial);
        return `<div class="card-icon"><i class="brei-icon fa-sm fa-regular fa-clock"></i><span>${daysOfTheWeek} ${meetingTime}</span></div>`;
    }

    let generateTutorialDateRangeHTML = (tutorial) => {
        let dateRange = friendlyTutorialDateRange(tutorial);
        return `<div class="card-icon"><i class="brei-icon fa-sm fa-regular fa-calendar"></i><span>${dateRange}</span></div>`;
    }

    let generateSectionFeeHTML = (section) => {
        let feeTotal = friendlySectionFeeTotal(section);
        return `<div class="card-icon"><i class="brei-icon fa-sm fa-regular fa-credit-card"></i><span>$${feeTotal}</span></div>`;
    }
    let generateSectionCreditsHTML = (section) => {
        return `<div class="card-icon"><i class="brei-icon fa-sm fa-regular fa-graduation-cap"></i><span>${section.credits} credits</span></div>`;
    }

    var html = '<ul class="sections-list">';
    sections.forEach(s => {
        console.log({ 'generateSectionsListHTML forEach s': s });
        const section = s.section;
        var deduplicatedTutorialsList = [];
        if (section.tutorials !== null && section.tutorials !== undefined) {
            deduplicatedTutorialsList = section.tutorials.filter((item, index, self) =>
                index === self.findIndex((t) =>
                    JSON.stringify(t) === JSON.stringify(item)
                )
            );
        }
        let listItem = `<li data-section-object-id="${section.id}">`;
        listItem += '<div>';
        listItem += `<strong>${section.sectionID} - ${section.sectionTitle}</strong>`;

        // if undefined or null
        if (section.sectionSummaryBrief !== null && section.sectionSummaryBrief !== undefined) {
            listItem += `<p>${section.sectionSummaryBrief}</p>`;
        }

        if (section.sectionSummaryLong !== null && section.sectionSummaryLong !== undefined) {
            listItem += `<p>${section.sectionSummaryLong}</p>`;
        }
        listItem += generateSectionFeeHTML(section);
        listItem += generateSectionCreditsHTML(section);
        listItem += '<div class="section-tutorials">';
        listItem += deduplicatedTutorialsList.map(tutorial => {
            let tutorialElement = '<div class="section-tutorial">';
            tutorialElement += generateTutorialDateRangeHTML(tutorial);
            tutorialElement += generateTutorialMeetingTimesHTML(tutorial);
            tutorialElement += generateTutorialTutorHTML(tutorial);
            tutorialElement += '</div>';
            return tutorialElement;
        }).join('');
        listItem += '</div>'; // end section-tutorials
        listItem += '</div>'; // end section
        listItem += '</li>';
        html += listItem;
    });
    html += '</ul>';
    return html;
}

const showProgramInstanceDetailsModal = ({
    instanceObjectID,
    instanceProgramInstanceID,
    instanceFee,
    instanceCredits,
    instanceTitle,
    instanceSummaryBrief = null,
    instanceSummaryLong = null,
    instanceSectionsJSON = '',
}) => {
    console.log({
        'showProgramInstanceDetailsModal': {
            instanceObjectID,
            instanceProgramInstanceID,
            instanceFee,
            instanceCredits,
            instanceTitle,
            instanceSummaryBrief,
            instanceSummaryLong,
            instanceSectionsJSON
        }
    });
    const modal = document.getElementById('program-instance-modal');



    document.getElementById('program-instance-modal--instance-object-id').textContent = instanceObjectID;
    document.getElementById('program-instance-modal--instance-program-instance-id').textContent = instanceProgramInstanceID;
    document.getElementById('program-instance-modal--fee').innerHTML = `<strong>${instanceFee} (full bundle)</strong>`;
    document.getElementById('program-instance-modal--title').textContent = instanceTitle;
    document.getElementById('program-instance-modal--credits').textContent = `${instanceCredits} credits total`;
    document.getElementById('program-instance-modal--sections-info').innerHTML = generateSectionsListHTML(instanceSectionsJSON);
    document.getElementById('program-instance-modal--sections-count').textContent = instanceSectionsJSON.length > 1 ? `${instanceSectionsJSON.length} class sections included` : `${instanceSectionsJSON.length} class section included`;
    if (instanceSummaryBrief === null || instanceSummaryBrief === '' || instanceSummaryBrief === undefined) {
        instanceSummaryBrief = 'No brief summary available';
    }
    if (instanceSummaryLong === null || instanceSummaryLong === '' || instanceSummaryLong === undefined) {
        instanceSummaryLong = 'No detailed summary available';
    }
    document.getElementById('program-instance-modal--summary-brief').innerHTML = instanceSummaryBrief;
    document.getElementById('program-instance-modal--summary-long').innerHTML = instanceSummaryLong;
    $(modal).modal({ fadeDuration: 100 });
}

var cart = {
    toggleInstanceDetails: ({ btn, instanceObjectID }) => {
        console.log({ 'cart.toggleInstanceDetails': { instanceObjectID } })
        let details = $(`#cart-item-details-${instanceObjectID}`);

        // show/hide the details section for the item in the cart
        if (details.hasClass('open') && $(btn).text() === 'Hide Details') {
            $(btn).text('View Details');
            details.css({
                'padding': '0',
                'border-bottom-color': '#eee',
                'border-width': '0',
                'background': '#f9f9f9',
                'height': '0',
                'text-align': 'left',
                'transition': 'all 0.05s ease',
                'overflow': 'hidden',
            });
            setTimeout(() => { details.height('0px'); }, 100)
        } else {
            $(btn).text('Hide Details');
            // add to shared CSS
            let naturalHeight = details.css('height', 'auto').height();
            details.css({
                'border-bottom-color': '#eee',
                'border-width': '1px',
                'background': '#f9f9f9',
                'height': 'auto',
                'padding': '10px 0 10px 10px',
                'text-align': 'left',
                'transition': 'all 0.05s ease',
                'overflow': 'hidden',
            });
            details.addClass('open').height(naturalHeight); // Ensure this is after the CSS transition 
        }
    },

    empty: () => {
        // remove all cart program instances from session storage
        // and empty cart 
        console.log({ 'cart.empty': '' })
        sessionStorage.removeItem(CART_PROGRAM_INSTANCES_SESSION_KEY);
        cart.refresh();
        cart.showMsg('Cart is now empty');
    },
    removeProgramInstance: ({ instanceObjectID }) => {
        // get existing cart program instances from session storage,
        // then remove the item and update session storage
        console.log({ 'cart.removeProgramInstance': { instanceObjectID } })
        let cartInstances = getSessionCartProgramInstances();
        // remove the instance ID from the cart
        if (!cartInstances.some(instance => instance.instanceObjectID === instanceObjectID)) {
            cart.showMsg('Program instance is not in your cart');
            return;
        }
        cartInstances = cartInstances.filter(instance => instance.instanceObjectID !== instanceObjectID);
        updateSessionCartProgramInstances(cartInstances);
        cart.refresh();
        cart.showMsg('Program instance removed from cart');
    },
    addProgramInstance: ({
        instanceObjectID,
        instanceProgramInstanceID,
        instanceTitle,
        instanceFee,
        instanceSectionsJSON,
        addedToWaitlist = false,
    }) => {
        // get existing cart program instances from session storage,
        // then add new item and update session storage
        console.log({
            'cart.addProgramInstance': {
                'instanceObjectID': instanceObjectID,
                'instanceProgramInstanceID': instanceProgramInstanceID,
                'instanceTitle': instanceTitle,
                'instanceFee': instanceFee,
                'instanceSectionsJSON': instanceSectionsJSON,
                'addedToWaitlist': addedToWaitlist
            }
        })
        let cartInstances = getSessionCartProgramInstances();
        const exists = cartInstances.some(instance => instance.instanceObjectID === instanceObjectID);
        console.log({ 'cart.addProgramInstance': { 'exists': exists, 'cartInstances': cartInstances } });
        if (exists) {
            cart.showMsg('Program instance is already in your cart');
            return;
        }
        cartInstances.push({
            'instanceObjectID': instanceObjectID,
            'instanceProgramInstanceID': instanceProgramInstanceID,
            'instanceTitle': instanceTitle,
            'instanceFee': instanceFee,
            'instanceSectionsJSON': instanceSectionsJSON,
            'addedToWaitlist': addedToWaitlist,
        });
        updateSessionCartProgramInstances(cartInstances);
        cart.refresh();
        cart.showMsg('Program instance added to cart');
    },
    showMsg: (msg) => {
        console.log({ 'cart.showMsg': msg })
        // display a message with toast
        $.toast(msg)
    },
    initialize: () => {
        // initialize cart
        console.log({ 'cart.initialize': '' })
        const closeCart = () => {
            $('#cart-wrapper').removeClass('open');
        };
        const openCart = () => {
            $('#cart-wrapper').addClass('open');
        }
        // add event listeners to buttons and links
        document.querySelectorAll('.cart-close').forEach(el => {
            el.addEventListener('click', function () {
                closeCart();
            });
        });
        // add event listeners to buttons and links
        document.querySelectorAll('.empty-cart').forEach(el => {
            el.addEventListener('click', function () {
                cart.empty();
            });
        });
        document.querySelector('#my-cart-toggle-button').addEventListener('click', function () {
            if ($('#cart-wrapper').css('opacity') == 0) {
                openCart();
            } else {
                closeCart();
            };
        });

        // initial cart refresh
        cart.refresh();
    },
    refresh: () => {
        console.log({ 'cart.refresh': '' })
        // get cartProgramInstances from session storage and update the cart UI
        const cartProgramInstances = getSessionCartProgramInstances();
        const cartCount = cartProgramInstances.length;
        $('.cart-sum').html(cartCount);
        const cartTotal = cartProgramInstances.reduce((acc, instance) => acc + parseInt(instance.instanceFee), 0);
        var i = 1;
        // build queryString ?id1=123&id2=456 where each id comes from instance.instanceObjectID in cartProgramInstances
        const queryString = cartProgramInstances.reduce((acc, instance) => {
            acc += `id${i}=${instance.instanceObjectID}&`;
            i++;
            return acc;
        }, '?').slice(0, -1); // remove trailing &
        const cartLink = `${ELEVATE_SHOP_URL}${queryString}`;
        // show - hide - update BTN & sum text
        console.log({ 'cart.refresh': { 'cartCount': cartCount, 'cartTotal': cartTotal, 'cartProgramInstances': cartProgramInstances } });

        // enable all add-to-cart-btns before disabling the ones that are in the cart
        $('button.add-to-cart-btn').text('Add to Cart (Register)');
        $('button.add-to-cart-btn').prop('disabled', false);

        $('button.join-waitlist-btn').text('Add to Cart (Join Waitlist)');
        $('button.join-waitlist-btn').prop('disabled', false);

        // add a list item to UI cart for each program instance in session storage
        // this will empty the cart list and re-populate it with the current cartProgramInstances 
        // which could be empty 
        $('#cart-items').html(cartProgramInstances.map(instance => {
            const listItem = document.createElement('li');
            const flexSummary = document.createElement('div');
            flexSummary.classList.add('flex-summary');
            const summaryText = document.createElement('div');
            summaryText.innerHTML = `<p>${instance.instanceTitle} ${instance.instanceProgramInstanceID}</p>`
            summaryText.innerHTML += `<p>$${instance.instanceFee} ${instance.addedToWaitlist ? ' (Waitlist) ' : ''}</p>`;
            summaryText.innerHTML += `<p>${instance.instanceSectionsJSON.length} ${instance.instanceSectionsJSON.length > 1 ? "class sections" : "class section"} included</p>`



            const viewDetailsLink = document.createElement('button');
            viewDetailsLink.classList.add('cart-view-item-details');
            viewDetailsLink.setAttribute('data-instance-id', instance.instanceObjectID);
            viewDetailsLink.textContent = 'View Details';
            // cannot use addEventListener because that doesn't show up in the returned HTML string
            $(viewDetailsLink).attr('onclick', `cart.toggleInstanceDetails({btn: this, instanceObjectID: '${instance.instanceObjectID}' })`);

            const removeLink = document.createElement('button');
            removeLink.classList.add('cart-remove');
            removeLink.setAttribute('data-remove-instance-id', instance.instanceObjectID);
            removeLink.textContent = 'Remove';
            // cannot use addEventListener because that doesn't show up in the returned HTML string
            $(removeLink).attr('onclick', `cart.removeProgramInstance({ instanceObjectID: '${instance.instanceObjectID}' })`);

            flexSummary.appendChild(summaryText);
            flexSummary.appendChild(viewDetailsLink);
            flexSummary.appendChild(removeLink);

            listItem.appendChild(flexSummary);

            // add a details section that is togglable with view/hide details link
            const detailsSection = document.createElement('div');
            detailsSection.classList.add('cart-item-details');
            detailsSection.setAttribute('id', `cart-item-details-${instance.instanceObjectID}`);
            detailsSection.innerHTML = generateSectionsListHTML(instance.instanceSectionsJSON);
            if (instance.addedToWaitlist) {
                detailsSection.innerHTML += `<p>Added to waitlist</p>`;
            }
            listItem.appendChild(detailsSection);


            return listItem.outerHTML;
        }).join(''));

        // update the add to cart button to prevent duplicate adds  
        cartProgramInstances.forEach((instance) => {
            $(`button.add-to-cart-btn[data-add-program-instance-to-cart-instance-id="${instance.instanceObjectID}"]`).text('Added to cart');
            $(`button.add-to-cart-btn[data-add-program-instance-to-cart-instance-id="${instance.instanceObjectID}"]`).prop('disabled', true);

            $(`button.join-waitlist-btn[data-add-program-instance-to-cart-instance-id="${instance.instanceObjectID}"]`).text('Joined Waitlist ($0)');
            $(`button.join-waitlist-btn[data-add-program-instance-to-cart-instance-id="${instance.instanceObjectID}"]`).prop('disabled', true);
        })

        // if at least one item in cart, show checkout button 
        if (cartCount >= 1) {
            $('#cart-checkout').show();
            $('#cart-checkout').animate({ 'opacity': 1 }, 200);
            $('#checkout-sum').text(`$${parseFloat(cartTotal, 10).toFixed(2).replace(/(\d)(?=(\d{3})+\.)/g, "$1,").toString()}`);
            $('#cart-checkout').attr({ 'href': cartLink });
        }
        else {
            $('#cart-checkout').animate({ 'opacity': 0 }, 200, function () { $(this).hide() });
            $('#checkout-sum').text('Cart is empty');
        }
    },
}

$(function () {/* initialize cart */ cart.initialize(); }); 