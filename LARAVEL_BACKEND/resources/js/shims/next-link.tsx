import { Link as InertiaLink, type InertiaLinkProps } from '@inertiajs/react'
import { forwardRef, type AnchorHTMLAttributes, type ReactNode } from 'react'

type NextLinkProps = Omit<InertiaLinkProps, 'href'> & {
  href: string
  children?: ReactNode
} & AnchorHTMLAttributes<HTMLAnchorElement>

const Link = forwardRef<HTMLAnchorElement, NextLinkProps>(function Link(
  { href, children, prefetch, onError, ...props },
  ref,
) {
  return (
    <InertiaLink
      ref={ref}
      href={href}
      prefetch={prefetch}
      onError={(errors) => {
        onError?.(errors)
        // Network / version mismatch: fall back to a full document load.
        if (typeof window !== 'undefined' && href.startsWith('/')) {
          window.location.assign(href)
        }
      }}
      {...props}
    >
      {children}
    </InertiaLink>
  )
})

export default Link
