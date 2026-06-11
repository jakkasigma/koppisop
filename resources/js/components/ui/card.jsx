import * as React from "react"

const cardStyle = {
  borderRadius: "0.5rem",
  border: "1px solid var(--border)",
  background: "var(--card)",
  color: "var(--card-foreground)",
  boxShadow: "0 1px 3px 0 rgb(0 0 0 / 0.1)",
  overflow: "hidden",
}

const Card = React.forwardRef(({ className = "", style = {}, ...props }, ref) => (
  <div
    ref={ref}
    style={{ ...cardStyle, ...style }}
    {...props}
  />
))
Card.displayName = "Card"

const CardHeader = React.forwardRef(({ className = "", style = {}, ...props }, ref) => (
  <div
    ref={ref}
    style={{ display: "flex", flexDirection: "column", gap: "0.375rem", padding: "1.5rem", ...style }}
    {...props}
  />
))
CardHeader.displayName = "CardHeader"

const CardTitle = React.forwardRef(({ className = "", style = {}, ...props }, ref) => (
  <h3
    ref={ref}
    style={{ fontSize: "1.125rem", fontWeight: 600, lineHeight: 1, letterSpacing: "-0.01em", margin: 0, color: "var(--card-foreground)", ...style }}
    {...props}
  />
))
CardTitle.displayName = "CardTitle"

const CardDescription = React.forwardRef(({ className = "", style = {}, ...props }, ref) => (
  <p
    ref={ref}
    style={{ fontSize: "0.875rem", color: "var(--muted-foreground)", margin: 0, ...style }}
    {...props}
  />
))
CardDescription.displayName = "CardDescription"

const CardContent = React.forwardRef(({ className = "", style = {}, ...props }, ref) => (
  <div
    ref={ref}
    style={{ padding: "0 1.5rem 1.5rem", ...style }}
    {...props}
  />
))
CardContent.displayName = "CardContent"

const CardFooter = React.forwardRef(({ className = "", style = {}, ...props }, ref) => (
  <div
    ref={ref}
    style={{ display: "flex", alignItems: "center", padding: "0 1.5rem 1.5rem", ...style }}
    {...props}
  />
))
CardFooter.displayName = "CardFooter"

export { Card, CardHeader, CardFooter, CardTitle, CardDescription, CardContent }
