import { useState } from 'react'
import { defaultCountries, parseCountry, usePhoneInput } from 'react-international-phone'

const countries = defaultCountries.map(parseCountry)

export default function PhoneCountryInput({ value, onChange, error }) {
  const [search, setSearch] = useState('')
  const [menuOpen, setMenuOpen] = useState(false)

  const phoneInput = usePhoneInput({
    defaultCountry: 'np',
    value,
    onChange: ({ phone, country }) => {
      const digits = phone.replace(/\D/g, '')
      const number = digits.length > country.dialCode.length ? phone : ''

      onChange(number, country.iso2.toUpperCase())
    },
  })

  const filteredCountries = countries.filter((country) => {
    const query = search.trim().toLowerCase()

    return !query
      || country.name.toLowerCase().includes(query)
      || country.dialCode.includes(query.replace(/^\+/, ''))
      || country.iso2.includes(query)
  })

  function selectCountry(country) {
    phoneInput.setCountry(country.iso2)
    setSearch('')
    setMenuOpen(false)
  }

  return (
    <div className="field phone-field">
      <span>Phone number <span className="optional-label">Optional</span></span>
      <div className="phone-control">
        <button
          className="country-trigger"
          type="button"
          aria-label={`Select country, current ${phoneInput.country.name} +${phoneInput.country.dialCode}`}
          aria-expanded={menuOpen}
          aria-haspopup="listbox"
          onClick={() => setMenuOpen((open) => !open)}
        >
          <span className="country-flag" aria-hidden="true">{countryFlag(phoneInput.country.iso2)}</span>
          <span>+{phoneInput.country.dialCode}</span>
          <span className="country-chevron" aria-hidden="true">⌄</span>
        </button>
        <input
          ref={phoneInput.inputRef}
          className="phone-number-input"
          type="tel"
          name="phone_e164"
          autoComplete="tel"
          inputMode="tel"
          placeholder="Phone number"
          value={phoneInput.inputValue}
          onChange={phoneInput.handlePhoneValueChange}
          aria-invalid={Boolean(error)}
          aria-describedby={error ? 'phone_e164-error' : undefined}
        />
      </div>

      {menuOpen && (
        <div className="country-menu">
          <input
            autoFocus
            className="country-search"
            type="search"
            placeholder="Search country…"
            aria-label="Search countries"
            value={search}
            onChange={(event) => setSearch(event.target.value)}
          />
          <div className="country-options" role="listbox" aria-label="Countries">
            {filteredCountries.map((country) => (
              <button
                className="country-option"
                type="button"
                role="option"
                aria-selected={phoneInput.country.iso2 === country.iso2}
                key={country.iso2}
                onClick={() => selectCountry(country)}
              >
                <span className="country-flag" aria-hidden="true">{countryFlag(country.iso2)}</span>
                <span className="country-name">{country.name}</span>
                <span className="country-code">{country.dialCode}</span>
              </button>
            ))}
            {filteredCountries.length === 0 && <p className="country-empty">No countries found.</p>}
          </div>
        </div>
      )}
      {error && <small id="phone_e164-error" className="field-error">{error}</small>}
    </div>
  )
}

function countryFlag(iso2) {
  return String.fromCodePoint(...iso2.toUpperCase().split('').map((letter) => 127397 + letter.charCodeAt(0)))
}
